<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget;

use InvalidArgumentException;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\FailedMessages;
use Manuxi\SuluDashboardBundle\System\PendingMigrations;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use Manuxi\SuluDashboardBundle\System\SystemVersions;
use Manuxi\SuluDashboardBundle\Tab\TabRegistry;
use Manuxi\SuluDashboardBundle\Widget\System\SystemTabProvider;
use Manuxi\SuluDashboardBundle\Widget\System\SystemTileData;
use Manuxi\SuluDashboardBundle\Widget\System\SystemTileWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The tab "System" and its tiles: group, permission, columns, and what each tile hands to the browser.
 */
class SystemTilesTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private SystemCheckStore $store;

    protected function setUp(): void
    {
        $this->store = new SystemCheckStore(new ArrayAdapter());
    }

    private function data(string $environment = 'prod', bool $debug = false, ?PendingMigrations $migrations = null, ?FailedMessages $failed = null, int $maxAgeHours = 48): SystemTileData
    {
        return new SystemTileData(
            new SystemVersions('8.4.1', '7.4.2', '3.0.9'),
            $environment,
            $debug,
            $this->store,
            $migrations ?? new PendingMigrations(),
            $failed ?? new FailedMessages(),
            $maxAgeHours,
            null,
            static fn (): int => self::NOW,
        );
    }

    private function tile(string $name, ?SystemTileData $data = null): SystemTileWidget
    {
        return new SystemTileWidget($data ?? $this->data(), $name);
    }

    /**
     * @return list<SystemTileWidget>
     */
    private function tiles(?SystemTileData $data = null): array
    {
        $data ??= $this->data();

        return array_map(static fn (string $name): SystemTileWidget => new SystemTileWidget($data, $name), array_keys(SystemTileWidget::TILES));
    }

    private function save(AuditResult $audit, int $age = 3600): void
    {
        $this->store->save(new SystemCheck(self::NOW - $age, $audit, [['path' => 'public/uploads', 'bytes' => 1_610_612_736], ['path' => 'var', 'bytes' => 300]]));
    }

    /**
     * @return array{package: string, installed: ?string, id: ?string, cve: ?string, title: string, link: ?string, severity: ?string, reportedAt: ?string}
     */
    private function advisory(string $package, ?string $severity = null, ?string $cve = null): array
    {
        return ['package' => $package, 'installed' => '1.0.0', 'id' => 'PKSA-' . $package, 'cve' => $cve, 'title' => 'Bug in ' . $package, 'link' => 'https://example.com/' . $package, 'severity' => $severity, 'reportedAt' => '2026-10-18T14:45:00+00:00'];
    }

    public function testTheTabIsATabOfTilesOfTheGroupSystemWithoutPeriodAndLanguage(): void
    {
        $tab = new SystemTabProvider();

        $this->assertSame('system', $tab->getKey());
        $this->assertSame('system', $tab->getGroup());
        $this->assertSame('sulu_dashboard.system', $tab->getTitle());
        $this->assertSame(DashboardAdmin::VIEW, $tab->getViewType());
        $this->assertSame([], $tab->getPeriods());
        $this->assertFalse($tab->showLocale());
        $this->assertSame('sulu.settings.dashboard_system', $tab->getSecurityContext());
    }

    public function testTheTabExistsOnlyForUsersWithTheSystemPermission(): void
    {
        $tab = new SystemTabProvider();
        $without = $this->createMock(SecurityCheckerInterface::class);
        $without->method('hasPermission')->willReturnCallback(static fn (mixed $subject): bool => DashboardAdmin::SECURITY_CONTEXT === $subject);
        $with = $this->createMock(SecurityCheckerInterface::class);
        $with->method('hasPermission')->willReturn(true);

        $this->assertSame([], (new TabRegistry([$tab], $without))->available());
        $this->assertSame([$tab], (new TabRegistry([$tab], $with))->available());
    }

    public function testEveryTileBelongsToTheGroupAndNeedsThePermission(): void
    {
        $without = $this->createMock(SecurityCheckerInterface::class);
        $without->method('hasPermission')->willReturnCallback(static fn (mixed $subject): bool => DashboardAdmin::SECURITY_CONTEXT === $subject);
        $with = $this->createMock(SecurityCheckerInterface::class);
        $with->method('hasPermission')->willReturn(true);

        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $tiles = $this->tiles($this->data(migrations: $migrations, failed: $failed));

        $this->assertCount(7, $tiles);
        foreach ($tiles as $tile) {
            $this->assertSame('system', $tile->getGroup());
            $this->assertSame(DashboardAdmin::SYSTEM_SECURITY_CONTEXT, $tile->getSecurityContext());
            $this->assertStringStartsWith('sulu_system_', $tile->getKey());
        }

        // without the permission nothing of it is listed or served
        $registry = new WidgetRegistry($tiles, $without);
        $this->assertSame([], $registry->available('system'));
        $this->assertNull($registry->find('sulu_system_vulnerabilities'));

        $registry = new WidgetRegistry($tiles, $with);
        $this->assertCount(7, $registry->available('system'));
        $this->assertSame([], $registry->available(), 'the tiles of the tab are not on the overview');
        $this->assertSame('sulu_system_vulnerabilities', $registry->available('system')[0]->getKey());
    }

    public function testTheTableOfVulnerabilitiesIsAPinnedCustomTileInTheWideColumn(): void
    {
        $tile = $this->tile('vulnerabilities');

        $this->assertSame(WidgetTypes::CUSTOM, $tile->getType());
        $this->assertSame('main', $tile->getColumn());
        $this->assertTrue($tile->isPinned());
        $this->assertSame('sulu_dashboard.system_vulnerabilities', $tile->getTitle());

        foreach ($this->tiles() as $other) {
            if ('sulu_system_vulnerabilities' !== $other->getKey()) {
                $this->assertFalse($other->isPinned(), $other->getKey());
                $this->assertSame('side', $other->getColumn(), $other->getKey());
                $this->assertSame(WidgetTypes::LIST, $other->getType(), $other->getKey());
            }
        }
    }

    public function testMigrationsAndFailedMessagesTilesOnlyExistWhereThereIsSomethingToRead(): void
    {
        $this->assertFalse($this->tile('migrations')->isAvailable());
        $this->assertFalse($this->tile('failed_messages')->isAvailable());
        $this->assertTrue($this->tile('versions')->isAvailable());

        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $data = $this->data(migrations: $migrations, failed: $failed);

        $this->assertTrue($this->tile('migrations', $data)->isAvailable());
        $this->assertTrue($this->tile('failed_messages', $data)->isAvailable());
    }

    public function testAnUnknownTileIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SystemTileWidget($this->data(), 'nope');
    }

    public function testVulnerabilitiesWithoutAResultSayThatTheCheckNeverRanAndNameTheCommand(): void
    {
        $data = $this->tile('vulnerabilities')->getData('en');

        $this->assertSame('never', $data['status']);
        $this->assertSame([], $data['advisories']);
        $this->assertSame('php bin/adminconsole sulu:dashboard:system-check', $data['command']);
    }

    public function testVulnerabilitiesListEveryFieldOfTheTableTheWorstFirst(): void
    {
        $this->save(AuditResult::vulnerable([
            $this->advisory('z/low', 'low'),
            $this->advisory('b/unknown'),
            $this->advisory('a/critical', 'critical', 'CVE-2026-9'),
            $this->advisory('c/high', 'high'),
        ]));

        $data = $this->tile('vulnerabilities')->getData('en');

        $this->assertSame('vulnerable', $data['status']);
        $this->assertSame(['a/critical', 'c/high', 'z/low', 'b/unknown'], array_column($data['advisories'], 'package'));
        $this->assertSame(
            ['package' => 'a/critical', 'installed' => '1.0.0', 'id' => 'PKSA-a/critical', 'cve' => 'CVE-2026-9', 'title' => 'Bug in a/critical', 'link' => 'https://example.com/a/critical', 'severity' => 'critical', 'reportedAt' => '2026-10-18T14:45:00+00:00'],
            $data['advisories'][0],
        );
    }

    public function testVulnerabilitiesAreOkWithoutAdvisoriesAndListAbandonedPackages(): void
    {
        $this->save(AuditResult::ok([['package' => 'a/old', 'replacement' => 'a/new']]));

        $data = $this->tile('vulnerabilities')->getData('en');

        $this->assertSame('ok', $data['status']);
        $this->assertSame([], $data['advisories']);
        $this->assertSame([['package' => 'a/old', 'replacement' => 'a/new']], $data['abandoned']);
    }

    public function testAnAuditThatCouldNotRunNamesItsReason(): void
    {
        foreach ([AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_FAILED, AuditResult::ERROR_TIMEOUT, 'something else'] as $error) {
            $this->save(AuditResult::unavailable($error));
            $data = $this->tile('vulnerabilities')->getData('en');

            $expected = \in_array($error, [AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_TIMEOUT], true) ? $error : AuditResult::ERROR_FAILED;
            $this->assertSame('unavailable', $data['status'], $error);
            $this->assertSame('sulu_dashboard.system_audit_error_' . $expected, $data['reason'], $error);
        }
    }

    public function testVersionsAreShownAsPlainTextSoTheyAreNeverTakenForTranslationKeys(): void
    {
        $data = $this->tile('versions')->getData('en');

        $this->assertFalse($data['header'], 'key/value rows have no table head');
        $this->assertSame(
            [['title' => 'PHP', 'meta' => ['text' => '8.4.1']], ['title' => 'Symfony', 'meta' => ['text' => '7.4.2']], ['title' => 'Sulu', 'meta' => ['text' => '3.0.9']]],
            $data['rows'],
        );
    }

    public function testEnvironmentWarnsClearlyAboutDebugModeInProduction(): void
    {
        $rows = $this->tile('environment', $this->data('prod', true))->getData('en')['rows'];

        $this->assertSame('sulu_dashboard.system_debug_in_prod', $rows[0]['title']);
        $this->assertSame('error', $rows[0]['severity']);
        $this->assertSame(['title' => 'sulu_dashboard.system_debug', 'meta' => 'sulu_dashboard.system_on'], $rows[2]);

        $quiet = $this->tile('environment', $this->data('dev', true))->getData('en')['rows'];
        $this->assertSame(['title' => 'sulu_dashboard.system_mode', 'meta' => ['text' => 'dev']], $quiet[0]);
        $this->assertCount(2, $quiet);
        $this->assertCount(2, $this->tile('environment', $this->data('prod', false))->getData('en')['rows']);
    }

    public function testStorageShowsTheFoldersByRelativeNameAndSaysHowToMeasureWithoutAResult(): void
    {
        $this->assertSame([], $this->tile('storage')->getData('de')['rows']);
        $this->assertSame('sulu_dashboard.system_storage_none', $this->tile('storage')->getData('de')['empty']);

        $this->save(AuditResult::ok());
        $rows = $this->tile('storage')->getData('de')['rows'];

        $this->assertSame(['title' => ['text' => 'public/uploads'], 'meta' => ['text' => '1,5 GB']], $rows[0]);
        $this->assertSame(['title' => ['text' => 'var'], 'meta' => ['text' => '300 B']], $rows[1]);
    }

    public function testLastCheckShowsTheAgeAndAlwaysTheCommand(): void
    {
        $parameters = ['command' => 'php bin/adminconsole sulu:dashboard:system-check'];

        $never = $this->tile('last_check')->getData('en')['rows'][0];
        $this->assertSame(['title' => 'sulu_dashboard.system_not_run', 'severity' => 'warning', 'text' => ['key' => 'sulu_dashboard.system_not_run_hint', 'parameters' => $parameters]], $never);

        $this->save(AuditResult::ok(), 7200);
        $fresh = $this->tile('last_check')->getData('en')['rows'][0];
        $this->assertSame(['key' => 'sulu_dashboard.system_age_hours', 'parameters' => ['count' => 2]], $fresh['title']);
        $this->assertSame('ok', $fresh['severity']);
        $this->assertSame(['key' => 'sulu_dashboard.system_refresh_hint', 'parameters' => $parameters], $fresh['text']);

        $this->save(AuditResult::ok(), 3 * 86400);
        $stale = $this->tile('last_check')->getData('en')['rows'][0];
        $this->assertSame(['key' => 'sulu_dashboard.system_age_days', 'parameters' => ['count' => 3]], $stale['title']);
        $this->assertSame('warning', $stale['severity']);
        $this->assertSame('sulu_dashboard.system_stale_hint', $stale['text']['key']);

        $this->assertSame('ok', $this->tile('last_check', $this->data(maxAgeHours: 100))->getData('en')['rows'][0]['severity']);
    }

    public function testMigrationsAreAGreenRowOrAWarning(): void
    {
        $ok = $this->createMock(PendingMigrations::class);
        $ok->method('isAvailable')->willReturn(true);
        $ok->method('count')->willReturn(0);
        $this->assertSame([['title' => 'sulu_dashboard.system_migrations_ok', 'severity' => 'ok']], $this->tile('migrations', $this->data(migrations: $ok))->getData('en')['rows']);

        $open = $this->createMock(PendingMigrations::class);
        $open->method('isAvailable')->willReturn(true);
        $open->method('count')->willReturn(2);
        $row = $this->tile('migrations', $this->data(migrations: $open))->getData('en')['rows'][0];
        $this->assertSame(['key' => 'sulu_dashboard.system_migrations_pending', 'parameters' => ['count' => 2]], $row['title']);
        $this->assertSame('warning', $row['severity']);
    }

    public function testAFailingCheckIsShownAndNeverSwallowedNorLeaksDetails(): void
    {
        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willThrowException(new RuntimeException('mysql://user:hunter2@db.internal/app'));
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $failed->method('count')->willThrowException(new RuntimeException('doctrine://default?queue=hunter2'));
        $data = $this->data(migrations: $migrations, failed: $failed);

        $migrationRow = $this->tile('migrations', $data)->getData('en')['rows'][0];
        $failedRow = $this->tile('failed_messages', $data)->getData('en')['rows'][0];

        $this->assertSame('sulu_dashboard.system_migrations_error', $migrationRow['title']);
        $this->assertSame('sulu_dashboard.system_failed_error', $failedRow['title']);
        $this->assertSame('warning', $failedRow['severity']);
        $this->assertStringNotContainsString('hunter2', (string) json_encode([$migrationRow, $failedRow]));
    }

    public function testFailedMessagesAreAGreenRowOrAWarning(): void
    {
        $none = $this->createMock(FailedMessages::class);
        $none->method('isConfigured')->willReturn(true);
        $none->method('count')->willReturn(0);
        $this->assertSame('sulu_dashboard.system_failed_none', $this->tile('failed_messages', $this->data(failed: $none))->getData('en')['rows'][0]['title']);

        $some = $this->createMock(FailedMessages::class);
        $some->method('isConfigured')->willReturn(true);
        $some->method('count')->willReturn(3);
        $row = $this->tile('failed_messages', $this->data(failed: $some))->getData('en')['rows'][0];
        $this->assertSame(['key' => 'sulu_dashboard.system_failed_found', 'parameters' => ['count' => 3]], $row['title']);
    }

    public function testNoTileShowsSecretsOrAbsolutePaths(): void
    {
        $this->save(AuditResult::vulnerable([$this->advisory('a/b', 'high')]), 5 * 86400);

        $json = '';
        foreach ($this->tiles($this->data('prod', true)) as $tile) {
            $json .= json_encode($tile->getData('en'));
        }

        foreach (['mysql://', 'doctrine://', '/var/www', '/home/', 'DATABASE_URL', 'APP_SECRET', 'D:\\\\'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, $forbidden);
        }
    }
}
