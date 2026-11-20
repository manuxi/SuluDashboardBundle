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
