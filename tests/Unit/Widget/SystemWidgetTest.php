<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\FailedMessages;
use Manuxi\SuluDashboardBundle\System\PendingMigrations;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use Manuxi\SuluDashboardBundle\System\SystemVersions;
use Manuxi\SuluDashboardBundle\Widget\Builtin\SystemWidget;
use Manuxi\SuluDashboardBundle\Widget\System\SystemTileData;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The small tile "System" of the overview: a traffic light with a link to the tab.
 */
class SystemWidgetTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private SystemCheckStore $store;

    protected function setUp(): void
    {
        $this->store = new SystemCheckStore(new ArrayAdapter());
    }

    private function widget(
        string $environment = 'prod',
        bool $debug = false,
        ?PendingMigrations $migrations = null,
        ?FailedMessages $failed = null,
        int $maxAgeHours = 48,
    ): SystemWidget {
        return new SystemWidget(new SystemTileData(
            new SystemVersions('8.4.1', '7.4.2', '3.0.9'),
            $environment,
            $debug,
            $this->store,
            $migrations ?? new PendingMigrations(),
            $failed ?? new FailedMessages(),
            $maxAgeHours,
            null,
            static fn (): int => self::NOW,
        ));
    }

    private function save(AuditResult $audit, int $age = 3600): void
    {
        $this->store->save(new SystemCheck(self::NOW - $age, $audit, [['path' => 'public/uploads', 'bytes' => 1_610_612_736]]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function problems(SystemWidget $widget): array
    {
        return array_values(array_filter($widget->getData('en')['rows'], static fn (array $row): bool => isset($row['severity'])));
    }

    public function testKeepsItsKeyAndIsAListTileOnlyForUsersWithTheSystemPermission(): void
    {
        $widget = $this->widget();

        $this->assertSame('sulu_system', $widget->getKey());
        $this->assertSame('list', $widget->getType());
        $this->assertSame('side', $widget->getColumn());
        $this->assertSame('sulu.settings.dashboard_system', $widget->getSecurityContext());
        $this->assertSame(DashboardAdmin::SYSTEM_SECURITY_CONTEXT, $widget->getSecurityContext());
    }

    public function testWithoutThePermissionTheTileIsNotOnTheDashboardAndItsDataIsNotServed(): void
    {
        $widget = $this->widget();
        $without = $this->createMock(SecurityCheckerInterface::class);
        $without->method('hasPermission')->willReturnCallback(static fn (mixed $subject): bool => DashboardAdmin::SECURITY_CONTEXT === $subject);
        $with = $this->createMock(SecurityCheckerInterface::class);
        $with->method('hasPermission')->willReturn(true);

        $registry = new WidgetRegistry([$widget], $without);
        $this->assertSame([], $registry->available());
        $this->assertNull($registry->find('sulu_system'));

        $registry = new WidgetRegistry([$widget], $with);
        $this->assertSame([$widget], $registry->available());
        $this->assertSame($widget, $registry->find('sulu_system'));
    }

    public function testAllInOrderIsAGreenRowWithTheAgeOfTheCheck(): void
    {
        $this->save(AuditResult::ok(), 7200);
        $data = $this->widget()->getData('en');

        $this->assertFalse($data['header']);
        $this->assertSame([['title' => 'sulu_dashboard.system_all_good', 'severity' => 'ok']], $this->problems($this->widget()));
        $last = end($data['rows']);
        $this->assertSame('sulu_dashboard.system_last_check', $last['title']);
        $this->assertSame(['key' => 'sulu_dashboard.system_age_hours', 'parameters' => ['count' => 2]], $last['meta']);
    }

    public function testLinksToTheTabSystemAsViewAndAttributesNeverAsUrl(): void
    {
        $this->save(AuditResult::ok());
        $footer = $this->widget()->getData('en')['footer'];

        $this->assertSame('sulu_dashboard.system_details', $footer['rows'][0]['title']);
        $this->assertSame('sulu_dashboard.tab_system', $footer['rows'][0]['link']['view']);
        $this->assertSame('{}', json_encode($footer['rows'][0]['link']['attributes']));
        $this->assertStringNotContainsString('http', (string) json_encode($footer));
    }

    public function testVulnerabilitiesAreRedWithTheirCount(): void
    {
        $this->save(AuditResult::vulnerable([$this->advisory('a/b'), $this->advisory('c/d')]));

        $problems = $this->problems($this->widget());

        $this->assertSame(['key' => 'sulu_dashboard.system_audit_found', 'parameters' => ['count' => 2]], $problems[0]['title']);
        $this->assertSame('error', $problems[0]['severity']);
        $this->assertCount(1, $problems);
    }

    public function testDebugModeInProductionIsRedAndNotElsewhere(): void
    {
        $this->save(AuditResult::ok());

        $problems = $this->problems($this->widget('prod', true));
        $this->assertSame('sulu_dashboard.system_debug_in_prod', $problems[0]['title']);
        $this->assertSame('error', $problems[0]['severity']);

        $this->assertSame('ok', $this->problems($this->widget('dev', true))[0]['severity']);
        $this->assertSame('ok', $this->problems($this->widget('prod', false))[0]['severity']);
    }

    public function testWithoutAResultTheTileSaysThatTheCheckNeverRan(): void
    {
        $data = $this->widget()->getData('en');

        $this->assertSame('sulu_dashboard.system_not_run', $data['rows'][0]['title']);
        $this->assertSame('warning', $data['rows'][0]['severity']);
        $this->assertSame('sulu_dashboard.system_never', end($data['rows'])['meta']);
    }

    public function testAnOldCheckIsAWarningAndTheLimitIsConfigurable(): void
    {
        $this->save(AuditResult::ok(), 3 * 86400);

        $this->assertSame('sulu_dashboard.system_stale', $this->problems($this->widget())[0]['title']);
        $this->assertSame('warning', $this->problems($this->widget())[0]['severity']);
        $this->assertSame('ok', $this->problems($this->widget(maxAgeHours: 100))[0]['severity']);
    }

    public function testAnAuditThatCouldNotRunAndAbandonedPackagesAreWarnings(): void
    {
        $this->save(AuditResult::unavailable(AuditResult::ERROR_TIMEOUT));
        $this->assertSame('sulu_dashboard.system_audit_unavailable', $this->problems($this->widget())[0]['title']);

        $this->save(AuditResult::ok([['package' => 'a/old', 'replacement' => null]]));
        $problems = $this->problems($this->widget());
        $this->assertSame(['key' => 'sulu_dashboard.system_abandoned_found', 'parameters' => ['count' => 1]], $problems[0]['title']);
        $this->assertSame('warning', $problems[0]['severity']);
    }

    public function testMigrationsAndFailedMessagesCountOnlyWhenTheyExist(): void
    {
        $this->save(AuditResult::ok());
        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willReturn(2);
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $failed->method('count')->willReturn(3);

        $this->assertSame('ok', $this->problems($this->widget())[0]['severity']);

        $titles = array_map(static fn (array $row): mixed => $row['title']['key'] ?? $row['title'], $this->problems($this->widget(migrations: $migrations, failed: $failed)));
        $this->assertSame(['sulu_dashboard.system_migrations_pending', 'sulu_dashboard.system_failed_found'], $titles);
    }

    public function testErrorsComeBeforeWarnings(): void
    {
        $this->save(AuditResult::vulnerable([$this->advisory('a/b')]), 5 * 86400);

        $severities = array_column($this->problems($this->widget('prod', true)), 'severity');

        $this->assertSame(['error', 'error', 'warning'], $severities);
    }

    public function testShowsNoSecretsAndNoAbsolutePaths(): void
    {
        $this->save(AuditResult::vulnerable([$this->advisory('a/b')]));
        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willThrowException(new RuntimeException('SQLSTATE: mysql://root:hunter2@db.internal:3306/app in /var/www/secret/project'));
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $failed->method('count')->willThrowException(new RuntimeException('doctrine://default?queue=hunter2 /home/user/app'));

        $json = (string) json_encode($this->widget('prod', true, $migrations, $failed)->getData('en'));

        foreach (['hunter2', 'mysql://', 'doctrine://', '/var/www', '/home/', 'secret', 'internal', 'DATABASE_URL', 'APP_SECRET'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, $forbidden);
        }
    }

    /**
     * @return array{package: string, installed: ?string, id: ?string, cve: ?string, title: string, link: ?string, severity: ?string, reportedAt: ?string}
     */
    private function advisory(string $package): array
    {
        return ['package' => $package, 'installed' => '1.0.0', 'id' => 'PKSA-1', 'cve' => null, 'title' => 'Bug', 'link' => null, 'severity' => null, 'reportedAt' => null];
    }
}
