<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Manuxi\SuluDashboardBundle\Command\SystemCheckCommand;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\AuditRunnerInterface;
use Manuxi\SuluDashboardBundle\System\MediaStatistics;
use Manuxi\SuluDashboardBundle\System\StorageMeasurer;
use Manuxi\SuluDashboardBundle\System\SystemChecker;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandTester;

class SystemCheckCommandTest extends TestCase
{
    private function tester(AuditResult $audit, ?SystemCheckStore $store = null): CommandTester
    {
        $runner = new class($audit) implements AuditRunnerInterface {
            public function __construct(private readonly AuditResult $result)
            {
            }

            public function run(): AuditResult
            {
                return $this->result;
            }
        };

        $checker = new SystemChecker($runner, new StorageMeasurer(sys_get_temp_dir() . '/sulu_dashboard_none', ['var']), $store ?? new SystemCheckStore(new ArrayAdapter()));

        return new CommandTester(new SystemCheckCommand($checker));
    }

    public function testSavesTheResultAndSucceeds(): void
    {
        $store = new SystemCheckStore(new ArrayAdapter());
        $tester = $this->tester(AuditResult::ok(), $store);

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('no known vulnerabilities', $tester->getDisplay());
        $this->assertSame(AuditResult::OK, $store->load()?->audit->status);
    }

    public function testVulnerabilitiesAreListedAndStillASuccessBecauseTheCheckWorked(): void
    {
        $tester = $this->tester(AuditResult::vulnerable([['package' => 'a/b', 'installed' => '1.0.0', 'id' => 'PKSA-1', 'cve' => 'CVE-1', 'title' => 'Bug', 'link' => null, 'severity' => null, 'reportedAt' => null]]));

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('1 known vulnerabilities', $tester->getDisplay());
        $this->assertStringContainsString('a/b: Bug (CVE-1)', $tester->getDisplay());
    }

    public function testACheckThatCouldNotRunFailsSoTheCronJobNoticesButIsStillSaved(): void
    {
        $store = new SystemCheckStore(new ArrayAdapter());
        $tester = $this->tester(AuditResult::unavailable(AuditResult::ERROR_NOT_FOUND), $store);

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Composer was not found', $tester->getDisplay());
        $this->assertSame(AuditResult::ERROR_NOT_FOUND, $store->load()?->audit->error);
    }

    public function testShowsTheMediaAndWhatChangedSinceTheRunBefore(): void
    {
        $directory = sys_get_temp_dir() . '/sulu_dashboard_cmd_' . bin2hex(random_bytes(4));
        mkdir($directory . '/var/cache', 0777, true);
        file_put_contents($directory . '/var/cache/a.bin', str_repeat('a', 1000));

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['media_count' => 3, 'media_bytes' => 2048]);
        $runner = new class implements AuditRunnerInterface {
            public function run(): AuditResult
            {
                return AuditResult::ok();
            }
        };
        $checker = new SystemChecker($runner, new StorageMeasurer($directory), new SystemCheckStore(new ArrayAdapter()), null, new MediaStatistics($connection));
        $tester = new CommandTester(new SystemCheckCommand($checker));

        try {
            $tester->execute([]);
            $this->assertStringContainsString('Storage var/cache: 1000 B', $tester->getDisplay());
            $this->assertStringContainsString('Media: 3 files, 2.0 KB', $tester->getDisplay());
            $this->assertStringNotContainsString('since the check', $tester->getDisplay());

            file_put_contents($directory . '/var/cache/b.bin', str_repeat('b', 5000));
            $tester->execute([]);
            $this->assertStringContainsString('Storage since the check 0 minutes ago: +4.9 KB', $tester->getDisplay());
        } finally {
            @unlink($directory . '/var/cache/a.bin');
            @unlink($directory . '/var/cache/b.bin');
            @rmdir($directory . '/var/cache');
            @rmdir($directory . '/var');
            @rmdir($directory);
        }
    }
}
