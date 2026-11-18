<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Command;

use Manuxi\SuluDashboardBundle\Command\SystemCheckCommand;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\AuditRunnerInterface;
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
}
