<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\System;

use DateTimeImmutable;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\AuditRunnerInterface;
use Manuxi\SuluDashboardBundle\System\ComposerOutdatedRunner;
use Manuxi\SuluDashboardBundle\System\PhpSupport;
use Manuxi\SuluDashboardBundle\System\RuntimeInfo;
use Manuxi\SuluDashboardBundle\System\StorageMeasurer;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemChecker;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use Manuxi\SuluDashboardBundle\System\UpdateRunnerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * What the tab "System" learned in the polish: the end of PHP's security support, the PHP runtime values and the newer
 * patch releases of Sulu and Symfony.
 */
class SystemPolishTest extends TestCase
{
    public function testPhpSupportKnowsTheEndOfEveryMinorVersionOfTheTable(): void
    {
        $now = new DateTimeImmutable('2026-11-19');

        $this->assertSame(['state' => PhpSupport::STATE_OK, 'until' => '2028-12-31'], PhpSupport::check('8.4.27', $now));
        $this->assertSame(['state' => PhpSupport::STATE_OK, 'until' => '2029-12-31'], PhpSupport::check('8.5.0', $now));
        $this->assertSame(['state' => PhpSupport::STATE_ENDED, 'until' => '2025-12-31'], PhpSupport::check('8.1.34', $now));
    }

    public function testPhpSupportWarnsHalfAYearBeforeTheEndAndNotEarlier(): void
    {
        // 8.2 ends on 2026-12-31: six weeks from now
        $this->assertSame(PhpSupport::STATE_ENDING, PhpSupport::check('8.2.30', new DateTimeImmutable('2026-11-19'))['state']);
        $this->assertSame(PhpSupport::STATE_ENDING, PhpSupport::check('8.2.30', new DateTimeImmutable('2026-07-05'))['state']);
        $this->assertSame(PhpSupport::STATE_OK, PhpSupport::check('8.2.30', new DateTimeImmutable('2026-06-30'))['state']);
        // the last day still counts as supported, the day after it does not
        $this->assertSame(PhpSupport::STATE_ENDING, PhpSupport::check('8.2.30', new DateTimeImmutable('2026-12-31 12:00'))['state']);
        $this->assertSame(PhpSupport::STATE_ENDED, PhpSupport::check('8.2.30', new DateTimeImmutable('2027-01-01'))['state']);
    }

    public function testPhpSupportOfVersionsBeforeAndAfterTheTable(): void
    {
        $now = new DateTimeImmutable('2026-11-19');

        $this->assertSame(['state' => PhpSupport::STATE_ENDED, 'until' => null], PhpSupport::check('7.2.1', $now));
        $this->assertSame(['state' => PhpSupport::STATE_UNKNOWN, 'until' => null], PhpSupport::check('9.0.0', $now));
        $this->assertSame(['state' => PhpSupport::STATE_UNKNOWN, 'until' => null], PhpSupport::check('nonsense', $now));
        $this->assertSame(PhpSupport::STATE_OK, PhpSupport::check('8.4.0-dev', $now)['state']);
    }

    public function testThePhpSupportTableIsSortedAndKnowsTheRunningVersion(): void
    {
        $dates = array_values(PhpSupport::SECURITY_UNTIL);
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates, 'the table is sorted by date');

        // the PHP that runs the tests is in the table: a new minor version has to be added to it
        $this->assertNotSame(PhpSupport::STATE_UNKNOWN, PhpSupport::check(\PHP_VERSION, new DateTimeImmutable())['state'], 'add ' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION . ' to PhpSupport::SECURITY_UNTIL');
    }

    public function testTheRuntimeReadsTheRunningProcess(): void
    {
        $runtime = RuntimeInfo::detect('de');

        $this->assertSame(\ini_get('memory_limit'), $runtime->memoryLimit);
        $this->assertSame((int) \ini_get('max_execution_time'), $runtime->maxExecutionTime);
        $this->assertSame(date_default_timezone_get(), $runtime->timezone);
        $this->assertSame('de', $runtime->locale);
    }

    /**
     * @dataProvider memoryLimits
     */
    public function testTheMemoryLimitIsReadInMegabytes(string $limit, ?int $megabytes): void
    {
        $this->assertSame($megabytes, (new RuntimeInfo($limit, 30, true, 'UTC', 'en'))->memoryLimitMegabytes());
    }

    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function memoryLimits(): iterable
    {
        yield '512M' => ['512M', 512];
        yield '2g' => ['2g', 2048];
        yield 'kilobytes' => ['262144K', 256];
        yield 'bytes' => ['134217728', 128];
        yield 'none' => ['-1', null];
        yield 'empty' => ['', null];
        yield 'nonsense' => ['lots', null];
    }

    public function testOnlySuluAndSymfonyOfTheOutdatedPackagesAreKept(): void
    {
        $updates = ComposerOutdatedRunner::parse([
            ['name' => 'sulu/sulu', 'version' => '3.0.9', 'latest' => '3.0.10'],
            ['name' => 'symfony/framework-bundle', 'version' => 'v7.4.2', 'latest' => 'v7.4.5'],
            ['name' => 'doctrine/orm', 'version' => '3.7.0', 'latest' => '3.7.1'],
            ['name' => 'sulu/sulu', 'version' => '3.0.9', 'latest' => '3.0.9'],
            ['name' => 'symfony/framework-bundle', 'version' => '7.4.2', 'latest' => '<script>'],
            'string',
            ['version' => '1.0.0'],
        ]);

        $this->assertSame([
            ['package' => 'sulu/sulu', 'installed' => '3.0.9', 'latest' => '3.0.10'],
            ['package' => 'symfony/framework-bundle', 'installed' => '7.4.2', 'latest' => '7.4.5'],
        ], $updates);
    }

    public function testAMissingComposerMeansNoAnswerAboutUpdatesNotAnError(): void
    {
        $this->assertNull((new ComposerOutdatedRunner(sys_get_temp_dir(), '/nonexistent/composer-' . bin2hex(random_bytes(4))))->run());
        $this->assertNull((new ComposerOutdatedRunner(sys_get_temp_dir(), '/nonexistent/composer.phar'))->run());
    }

    public function testTheCheckKeepsTheUpdatesAndReadsOlderResultsWithoutThem(): void
    {
        $updates = [['package' => 'sulu/sulu', 'installed' => '3.0.9', 'latest' => '3.0.10']];
        $check = new SystemCheck(1_800_000_000, AuditResult::ok(), [], null, null, $updates);

        $this->assertSame($updates, SystemCheck::fromArray($check->toArray())?->updates);

        $old = $check->toArray();
        unset($old['updates']);
        $this->assertNull(SystemCheck::fromArray($old)?->updates, 'a result of an earlier version has no answer about updates');

        $broken = $check->toArray();
        $broken['updates'] = [['package' => 'a/b'], 'x', ['package' => 'c/d', 'installed' => '1', 'latest' => '2']];
        $this->assertSame([['package' => 'c/d', 'installed' => '1', 'latest' => '2']], SystemCheck::fromArray($broken)?->updates);
    }

    public function testTheCheckerAsksForUpdatesAndSavesTheAnswer(): void
    {
        $directory = sys_get_temp_dir() . '/sulu_dashboard_polish_' . bin2hex(random_bytes(4));
        mkdir($directory, 0777, true);
        $audit = new class implements AuditRunnerInterface {
            public function run(): AuditResult
            {
                return AuditResult::ok();
            }
        };
        $updates = new class implements UpdateRunnerInterface {
            public function run(): array
            {
                return [['package' => 'sulu/sulu', 'installed' => '3.0.9', 'latest' => '3.0.10']];
            }
        };
        $store = new SystemCheckStore(new ArrayAdapter());

        $check = (new SystemChecker($audit, new StorageMeasurer($directory), $store, null, null, $updates))->run();

        $this->assertSame('3.0.10', $check->updates[0]['latest'] ?? null);
        $this->assertSame($check->updates, $store->load()?->updates);

        $this->assertNull((new SystemChecker($audit, new StorageMeasurer($directory), $store))->run()->updates, 'without a runner nothing is known about updates');
        rmdir($directory);
    }
}
