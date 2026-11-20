<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\System;

use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\AuditRunnerInterface;
use Manuxi\SuluDashboardBundle\System\ComposerAuditRunner;
use Manuxi\SuluDashboardBundle\System\ComposerEnvironment;
use Manuxi\SuluDashboardBundle\System\ComposerFailure;
use Manuxi\SuluDashboardBundle\System\ComposerOutdatedRunner;
use Manuxi\SuluDashboardBundle\System\StorageMeasurer;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemChecker;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Process\Process;

/**
 * The environment of the Composer process (no HOME in a web request) and the reason when Composer fails.
 */
class ComposerEnvironmentTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/sulu_dashboard_composer_' . bin2hex(random_bytes(4));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    private function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ('.' !== $entry && '..' !== $entry) {
                    $this->remove($path . '/' . $entry);
                }
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @param array<string, string> $env
     */
    private function environment(array $env, ?string $configured = null, bool $windows = false): ComposerEnvironment
    {
        return new ComposerEnvironment($this->directory . '/default-home', $configured, static fn (string $name): string|false => $env[$name] ?? false, $windows);
    }

    private function fakeComposer(string $php): string
    {
        $file = $this->directory . '/composer.phar';
        file_put_contents($file, "<?php\n" . $php);

        return $file;
    }

    public function testWithoutHomeComposerGetsAFolderOfItsOwnForHomeAndCache(): void
    {
        $variables = $this->environment([])->variables();

        $this->assertSame('1', $variables['COMPOSER_NO_INTERACTION']);
        $this->assertSame($this->directory . '/default-home', $variables['COMPOSER_HOME']);
        $this->assertSame($this->directory . '/default-home/cache', $variables['COMPOSER_CACHE_DIR']);
        $this->assertDirectoryExists($this->directory . '/default-home');
    }

    public function testAHomeThatIsThereIsLeftAlone(): void
    {
        $variables = $this->environment(['HOME' => $this->directory])->variables();
        $this->assertArrayNotHasKey('COMPOSER_HOME', $variables);
        $this->assertArrayNotHasKey('COMPOSER_CACHE_DIR', $variables);

        $variables = $this->environment(['COMPOSER_HOME' => '/set/by/the/server'])->variables();
        $this->assertSame('/set/by/the/server', $variables['COMPOSER_HOME']);
        $this->assertArrayNotHasKey('COMPOSER_CACHE_DIR', $variables);
        $this->assertDirectoryDoesNotExist($this->directory . '/default-home');
    }

    public function testAHomeThatDoesNotExistCountsAsMissing(): void
    {
        $variables = $this->environment(['HOME' => $this->directory . '/gone'])->variables();

        $this->assertSame($this->directory . '/default-home', $variables['COMPOSER_HOME']);
    }

    public function testOnWindowsAppDataAndLocalAppDataCount(): void
    {
        $appData = $this->directory . '/AppData';
        mkdir($appData);

        $this->assertArrayHasKey('COMPOSER_HOME', $this->environment(['APPDATA' => $appData], null, true)->variables());
        $this->assertArrayNotHasKey('COMPOSER_HOME', $this->environment(['APPDATA' => $appData, 'LOCALAPPDATA' => $appData, 'SystemRoot' => $this->directory], null, true)->variables());
        $this->assertArrayNotHasKey('COMPOSER_HOME', $this->environment(['HOME' => $appData], null, false)->variables());
    }

    public function testAUsableHomeIsPassedOnExplicitly(): void
    {
        $this->assertSame($this->directory, $this->environment(['HOME' => $this->directory])->variables()['HOME']);

        $appData = $this->directory . '/AppData';
        mkdir($appData);
        $variables = $this->environment(['APPDATA' => $appData, 'LOCALAPPDATA' => $appData, 'SystemRoot' => $this->directory], null, true)->variables();
        $this->assertSame($appData, $variables['APPDATA']);
        $this->assertSame($appData, $variables['LOCALAPPDATA']);
        $this->assertArrayNotHasKey('HOME', $variables);
    }

    public function testAHomeIsNotPassedOnWhereTheOwnFolderIsUsed(): void
    {
        $variables = $this->environment(['HOME' => $this->directory . '/gone'])->variables();
        $this->assertArrayNotHasKey('HOME', $variables);

        $variables = $this->environment(['HOME' => $this->directory], $this->directory . '/mine')->variables();
        $this->assertArrayNotHasKey('HOME', $variables);
    }

    public function testTheChildProcessGetsTheHomeEvenIfItIsNotInServer(): void
    {
        $name = 'Windows' === \PHP_OS_FAMILY ? 'APPDATA' : 'HOME';
        $names = 'Windows' === \PHP_OS_FAMILY ? ['APPDATA', 'LOCALAPPDATA'] : ['HOME'];
        $env = [];
        foreach ($names as $each) {
            $env[$each] = $this->directory;
        }
        $env['SystemRoot'] = $this->directory;
        $saved = $_SERVER;
        $savedEnv = $_ENV;

        try {
            foreach ($names as $each) {
                unset($_SERVER[$each], $_ENV[$each]);
            }
            // like a web server: the variable is there for getenv() (the closure stands in) but not in $_SERVER
            $variables = (new ComposerEnvironment($this->directory . '/default-home', null, static fn (string $n): string|false => $env[$n] ?? false))->variables();
            $process = new Process([\PHP_BINARY, '-r', "echo getenv('" . $name . "');"], null, $variables);
            $process->run();
        } finally {
            $_SERVER = $saved;
            $_ENV = $savedEnv;
        }

        $this->assertSame($this->directory, $process->getOutput());
    }

    private function verboseGithubFailure(): string
    {
        $pad = static fn (string $line): string => '  ' . str_pad($line, 117) . "\n";

        return "Reading ./composer.json (/srv/app/composer.json)\n"
            . "Checked CA file /etc/ssl/cacert.pem: valid\n"
            . "Executing command (/srv/app): git branch -a --no-color --no-abbrev -v\n"
            . "Failed to initialize global composer: Composer could not find the config file\n"
            . "Loading plugin Symfony\Flex\Flex (from symfony/flex)\n"
            . "Downloading https://api.github.com/repos/example/ExampleBundle\n"
            . "[403] https://api.github.com/repos/example/ExampleBundle\n"
            . "Executing command (CWD): git clone --mirror -- git@github.com:example/ExampleBundle.git /tmp/vcs/x.git/\n"
            . "Failed to clone the git@github.com:example/ExampleBundle.git repository, try running in interactive mode\n\n"
            . "In Git.php line 595:\n\n"
            . $pad('') . $pad('[RuntimeException]') . $pad('Failed to execute git clone --mirror -- git@github.com:example/ExampleBundle.git /tmp/vcs/x.git/') . $pad('')
            . $pad("Cloning into bare repository '/tmp/vcs/x.git'...") . $pad('Host key verification failed.') . $pad('fatal: Could not read from remote repository.') . $pad('')
            . "\nupdate [--dry-run]\n";
    }

    public function testTheReasonIsTheExceptionBlockAndItsLastLineNotTheProgressMessages(): void
    {
        $reason = ComposerFailure::reason($this->verboseGithubFailure(), '/srv/app');

        $this->assertNotNull($reason);
        $this->assertStringStartsWith('Failed to execute git clone', $reason);
        $this->assertStringContainsString('fatal: Could not read from remote repository.', $reason);
        $this->assertStringNotContainsString('Reading', $reason);
        $this->assertStringNotContainsString('Executing command', $reason);
        $this->assertLessThanOrEqual(300, mb_strlen($reason));
    }

    public function testWithoutAnExceptionBlockOnlyTheProgressMessagesAreLeftOut(): void
    {
        $reason = ComposerFailure::reason("Reading ./composer.json (/srv/app/composer.json)\nLoading plugin A\nfatal: detected dubious ownership in repository\n", '/srv/app');

        $this->assertSame('fatal: detected dubious ownership in repository', $reason);
        $this->assertNull(ComposerFailure::reason("Reading ./composer.json\n \n", '/srv/app'));
    }

    public function testAGithubRepositoryWithoutATokenIsRecognised(): void
    {
        $this->assertTrue(ComposerFailure::isGithubAuth($this->verboseGithubFailure()));
        $this->assertTrue(ComposerFailure::isGithubAuth('Could not authenticate against github.com'));
        $this->assertFalse(ComposerFailure::isGithubAuth('Could not resolve host: repo.packagist.org'));
        $this->assertFalse(ComposerFailure::isGithubAuth('The APPDATA or COMPOSER_HOME environment variable must be set'));
    }

    public function testTheUpdateCheckNamesTheGithubTokenAndKeepsTheReason(): void
    {
        $composer = $this->fakeComposer('fwrite(STDERR, ' . var_export($this->verboseGithubFailure(), true) . '); exit(1);');
        $runner = new ComposerOutdatedRunner($this->directory, $composer, 30, $this->environment([]));

        $this->assertNull($runner->run());
        $failure = $runner->failure();
        $this->assertNotNull($failure);
        $this->assertStringContainsString('GitHub token', $failure);
        $this->assertStringContainsString('auth.json', $failure);
        $this->assertStringContainsString('Host key verification failed', $failure);
        $this->assertStringNotContainsString('exit code', $failure);
    }

    public function testTheChildProcessDoesNotInheritTheVerbosityOfThisProcess(): void
    {
        $saved = [$_SERVER['SHELL_VERBOSITY'] ?? null, $_ENV['SHELL_VERBOSITY'] ?? null, getenv('SHELL_VERBOSITY')];
        $_SERVER['SHELL_VERBOSITY'] = $_ENV['SHELL_VERBOSITY'] = '3';
        putenv('SHELL_VERBOSITY=3');

        try {
            $process = new Process([\PHP_BINARY, '-r', "echo getenv('SHELL_VERBOSITY');"], null, $this->environment(['HOME' => $this->directory])->variables());
            $process->run();
            $inherited = new Process([\PHP_BINARY, '-r', "echo getenv('SHELL_VERBOSITY');"]);
            $inherited->run();
        } finally {
            foreach ([&$_SERVER, &$_ENV] as $i => &$target) {
                null === $saved[$i] ? $target = array_diff_key($target, ['SHELL_VERBOSITY' => 1]) : $target['SHELL_VERBOSITY'] = $saved[$i];
            }
            putenv(false === $saved[2] ? 'SHELL_VERBOSITY' : 'SHELL_VERBOSITY=' . $saved[2]);
        }

        $this->assertSame('3', $inherited->getOutput(), 'without the override the child inherits the verbosity');
        $this->assertSame('0', $process->getOutput());
    }

    public function testAConfiguredFolderAlwaysWins(): void
    {
        $variables = $this->environment(['HOME' => $this->directory, 'COMPOSER_HOME' => '/set/by/the/server'], $this->directory . '/mine')->variables();

        $this->assertSame($this->directory . '/mine', $variables['COMPOSER_HOME']);
        $this->assertSame($this->directory . '/mine/cache', $variables['COMPOSER_CACHE_DIR']);
        $this->assertDirectoryExists($this->directory . '/mine');
    }

    public function testTheAuditRunnerStartsComposerWithAHomeAfterAllWhenThereIsNone(): void
    {
        $composer = $this->fakeComposer("file_put_contents(__DIR__ . '/seen.txt', getenv('COMPOSER_HOME') . '|' . getenv('COMPOSER_CACHE_DIR') . '|' . getenv('COMPOSER_NO_INTERACTION')); echo '{\"advisories\":[]}';");

        $result = (new ComposerAuditRunner($this->directory, $composer, 30, null, $this->environment([])))->run();

        $this->assertSame(AuditResult::OK, $result->status);
        $home = $this->directory . '/default-home';
        $this->assertSame($home . '|' . $home . '/cache|1', file_get_contents($this->directory . '/seen.txt'));
    }

    public function testANetworkErrorIsNamedAndKeepsComposersWordsWithoutPaths(): void
    {
        $stderr = "In CurlDownloader.php line 394:\n  curl error 6 while downloading https://repo.packagist.org/packages.json: Could not resolve host: repo.packagist.org in " . $this->directory . '/vendor/composer/x.php and C:\\Windows\\Temp\\y.txt';
        $composer = $this->fakeComposer('fwrite(STDERR, ' . var_export($stderr, true) . '); exit(100);');

        $result = (new ComposerAuditRunner($this->directory, $composer, 30, null, $this->environment([])))->run();

        $this->assertSame(AuditResult::UNAVAILABLE, $result->status);
        $this->assertSame(AuditResult::ERROR_NETWORK, $result->error);
        $this->assertNotNull($result->detail);
        $this->assertStringContainsString('curl error 6', $result->detail);
        $this->assertStringContainsString('https://repo.packagist.org/packages.json', $result->detail);
        $this->assertStringContainsString('<project>/vendor/composer/x.php', $result->detail);
        $this->assertStringNotContainsString($this->directory, $result->detail);
        $this->assertStringNotContainsString('Windows', $result->detail);
    }

    public function testAnotherFailureKeepsTheShortenedErrorOutput(): void
    {
        $composer = $this->fakeComposer('fwrite(STDERR, "\e[37;41m The APPDATA or COMPOSER_HOME environment variable must be set \e[39;49m\n" . str_repeat("x ", 400)); exit(1);');

        $result = (new ComposerAuditRunner($this->directory, $composer, 30, null, $this->environment([])))->run();

        $this->assertSame(AuditResult::ERROR_FAILED, $result->error);
        $this->assertNotNull($result->detail);
        $this->assertStringStartsWith('The APPDATA or COMPOSER_HOME environment variable must be set', $result->detail);
        $this->assertStringNotContainsString("\e", $result->detail);
        $this->assertLessThanOrEqual(300, mb_strlen($result->detail));
    }

    public function testAFailureWithoutOutputNamesTheExitCode(): void
    {
        $composer = $this->fakeComposer('exit(7);');

        $result = (new ComposerAuditRunner($this->directory, $composer, 30, null, $this->environment([])))->run();

        $this->assertSame(AuditResult::ERROR_FAILED, $result->error);
        $this->assertSame('exit code 7', $result->detail);
    }

    public function testTheReasonIsSavedWithTheResult(): void
    {
        $result = AuditResult::fromArray(AuditResult::unavailable(AuditResult::ERROR_NETWORK, 'curl error 6')->toArray());

        $this->assertSame('curl error 6', $result?->detail);
        $this->assertNull(AuditResult::fromArray(AuditResult::unavailable(AuditResult::ERROR_FAILED)->toArray())?->detail);
    }

    public function testFailedUpdatesNameTheirReasonAndItIsSavedWithTheCheck(): void
    {
        $composer = $this->fakeComposer('fwrite(STDERR, "curl error 7 while downloading https://repo.packagist.org/packages.json: Failed to connect"); exit(100);');
        $updates = new ComposerOutdatedRunner($this->directory, $composer, 30, $this->environment([]));
        $store = new SystemCheckStore(new ArrayAdapter());
        $audit = new class implements AuditRunnerInterface {
            public function run(): AuditResult
            {
                return AuditResult::ok();
            }
        };

        $check = (new SystemChecker($audit, new StorageMeasurer($this->directory, ['var']), $store, null, null, $updates))->run();

        $this->assertNull($check->updates);
        $this->assertNotNull($check->updatesError);
        $this->assertStringContainsString('network access of the web PHP', $check->updatesError);
        $this->assertStringContainsString('curl error 7', $check->updatesError);
        $this->assertSame($check->updatesError, $store->load()?->updatesError);
        $this->assertSame($check->updatesError, SystemCheck::fromArray($check->toArray())?->updatesError);
    }

    public function testASuccessfulUpdateCheckLeavesNoReason(): void
    {
        $composer = $this->fakeComposer('echo \'{"locked":[]}\';');
        $runner = new ComposerOutdatedRunner($this->directory, $composer, 30, $this->environment([]));

        $this->assertSame([], $runner->run());
        $this->assertNull($runner->failure());
    }

    public function testSecretsAndForeignPathsAreHidden(): void
    {
        $text = ComposerFailure::summarize('Could not authenticate against https://user:s3cret@example.org/x token=abc123 password: hunter2 ghp_AbCdEf123456 at /home/someone/.composer/auth.json', '/srv/project');

        $this->assertNotNull($text);
        $this->assertStringNotContainsString('s3cret', $text);
        $this->assertStringNotContainsString('abc123', $text);
        $this->assertStringNotContainsString('hunter2', $text);
        $this->assertStringNotContainsString('ghp_', $text);
        $this->assertStringNotContainsString('/home/someone', $text);
        $this->assertStringContainsString('https://***@example.org/x', $text);
        $this->assertNull(ComposerFailure::summarize("  \n ", '/srv/project'));
    }

    public function testNetworkFailuresAreRecognised(): void
    {
        $this->assertTrue(ComposerFailure::isNetwork('curl error 28 while downloading'));
        $this->assertTrue(ComposerFailure::isNetwork('Could not resolve host: repo.packagist.org'));
        $this->assertFalse(ComposerFailure::isNetwork('The APPDATA or COMPOSER_HOME environment variable must be set'));
    }
}
