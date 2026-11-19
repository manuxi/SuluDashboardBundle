<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget;

use InvalidArgumentException;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\FailedMessages;
use Manuxi\SuluDashboardBundle\System\PendingMigrations;
use Manuxi\SuluDashboardBundle\System\RuntimeInfo;
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

    private function data(string $environment = 'prod', bool $debug = false, ?PendingMigrations $migrations = null, ?FailedMessages $failed = null, int $maxAgeHours = 48, string $php = '8.4.1', ?RuntimeInfo $runtime = null): SystemTileData
    {
        return new SystemTileData(
            new SystemVersions($php, '7.4.2', '3.0.9'),
            $environment,
            $debug,
            $this->store,
            $migrations ?? new PendingMigrations(),
            $failed ?? new FailedMessages(),
            $maxAgeHours,
            null,
            static fn (): int => self::NOW,
            null,
            $runtime ?? new RuntimeInfo('256M', 30, true, 'UTC', 'en'),
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

        $tiles = $this->tiles();

        $this->assertCount(5, $tiles);
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
        $this->assertCount(5, $registry->available('system'));
        $this->assertSame([], $registry->available(), 'the tiles of the tab are not on the overview');
        $this->assertSame('sulu_system_status', $registry->available('system')[0]->getKey(), 'the condition comes first');
        $this->assertNull($registry->find('sulu_system'), 'the traffic light has left the overview');
    }

    public function testTheConditionIsThePinnedFirstTileInTheWideColumnAndTheFactsAreCustomTilesInTheNarrowOne(): void
    {
        $status = $this->tile('status');

        $this->assertSame(WidgetTypes::CUSTOM, $status->getType());
        $this->assertSame('main', $status->getColumn());
        $this->assertTrue($status->isPinned());
        $this->assertSame('sulu_dashboard.system_status', $status->getTitle());
        $this->assertGreaterThan($this->tile('vulnerabilities')->getPriority(), $status->getPriority());

        foreach (['versions', 'environment'] as $name) {
            $this->assertSame(WidgetTypes::CUSTOM, $this->tile($name)->getType(), $name);
            $this->assertSame('side', $this->tile($name)->getColumn(), $name);
            $this->assertTrue($this->tile($name)->isPinned(), $name);
        }
    }

    public function testTheTableOfVulnerabilitiesIsAPinnedCustomTileInTheWideColumn(): void
    {
        $tile = $this->tile('vulnerabilities');

        $this->assertSame(WidgetTypes::CUSTOM, $tile->getType());
        $this->assertSame('main', $tile->getColumn());
        $this->assertTrue($tile->isPinned());
        $this->assertSame('sulu_dashboard.system_vulnerabilities', $tile->getTitle());
    }

    public function testTheStorageIsACustomTileInTheWideColumnThatStaysThere(): void
    {
        $tile = $this->tile('storage');

        $this->assertSame(WidgetTypes::CUSTOM, $tile->getType());
        $this->assertSame('main', $tile->getColumn());
        $this->assertTrue($tile->isPinned());
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
        foreach ([AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_FAILED, AuditResult::ERROR_TIMEOUT, AuditResult::ERROR_NETWORK, 'something else'] as $error) {
            $this->save(AuditResult::unavailable($error));
            $data = $this->tile('vulnerabilities')->getData('en');

            $expected = \in_array($error, [AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_TIMEOUT, AuditResult::ERROR_NETWORK], true) ? $error : AuditResult::ERROR_FAILED;
            $this->assertSame('unavailable', $data['status'], $error);
            $this->assertSame('sulu_dashboard.system_audit_error_' . $expected, $data['reason'], $error);
        }
    }

    public function testStorageSaysHowToMeasureWithoutAResultAndShowsTheFoldersWithOne(): void
    {
        $this->assertSame(['status' => 'never', 'command' => 'php bin/adminconsole sulu:dashboard:system-check'], $this->tile('storage')->getData('de'));

        $this->save(AuditResult::ok());
        $data = $this->tile('storage')->getData('de');

        $this->assertSame('ok', $data['status']);
        $this->assertSame('1,5 GB', $data['total']['text']);
        $this->assertSame('sulu_dashboard.system_storage_uploads', $data['items'][0]['label']);
        $this->assertSame('public/uploads', $data['items'][0]['path']);
    }

    /**
     * @return array<string, array<string, mixed>> the rows of the condition by their title (a string or the key of it)
     */
    private function conditionRows(SystemTileData $data): array
    {
        $rows = [];
        foreach ($data->status('en')['rows'] as $row) {
            $rows[\is_array($row['title']) ? $row['title']['key'] : $row['title']] = $row;
        }

        return $rows;
    }

    public function testTheConditionIsGreenWhenEverythingIsInOrderAndListsEveryCheck(): void
    {
        $this->save(AuditResult::ok(), 7200);
        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willReturn(0);
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $failed->method('count')->willReturn(0);

        $status = $this->data(migrations: $migrations, failed: $failed)->status('en');

        $this->assertSame('ok', $status['status']);
        $this->assertSame('sulu_dashboard.system_all_good', $status['headline']);
        $this->assertSame(
            ['sulu_dashboard.system_vulnerabilities', 'sulu_dashboard.system_packages', 'sulu_dashboard.system_php_support', 'sulu_dashboard.system_debug', 'sulu_dashboard.system_migrations', 'sulu_dashboard.system_failed_messages', 'sulu_dashboard.system_last_check'],
            array_column($status['rows'], 'title'),
        );
        $this->assertSame(['ok'], array_unique(array_column($status['rows'], 'severity')), 'every row of a green condition has the green check');
        $this->assertSame(['key' => 'sulu_dashboard.system_age_hours', 'parameters' => ['count' => 2]], end($status['rows'])['value']);
    }

    public function testMigrationsAndFailedMessagesHaveARowOnlyWhereTheyExist(): void
    {
        $this->save(AuditResult::ok());

        $titles = array_keys($this->conditionRows($this->data()));

        $this->assertNotContains('sulu_dashboard.system_migrations', $titles);
        $this->assertNotContains('sulu_dashboard.system_failed_messages', $titles);
    }

    public function testTheConditionWithoutAResultSaysThatNothingWasCheckedAndNamesTheCommand(): void
    {
        $status = $this->data()->status('en');
        $rows = $this->conditionRows($this->data());

        $this->assertSame('warning', $status['status']);
        $this->assertSame(['key' => 'sulu_dashboard.system_attention', 'parameters' => ['count' => 2]], $status['headline']);
        $this->assertSame('warning', $rows['sulu_dashboard.system_vulnerabilities']['severity']);
        $this->assertSame('sulu_dashboard.system_not_checked', $rows['sulu_dashboard.system_vulnerabilities']['value']);
        $this->assertArrayNotHasKey('sulu_dashboard.system_packages', $rows, 'nothing is known about packages yet');
        $this->assertSame('sulu_dashboard.system_never', $rows['sulu_dashboard.system_last_check']['value']);
        $this->assertSame('sulu_dashboard.system_not_run_hint', $rows['sulu_dashboard.system_last_check']['text']['key']);
        $this->assertSame('php bin/adminconsole sulu:dashboard:system-check', $rows['sulu_dashboard.system_last_check']['text']['parameters']['command']);
    }

    public function testVulnerabilitiesTurnTheConditionRedAndErrorsCountLikeWarnings(): void
    {
        $this->save(AuditResult::vulnerable([$this->advisory('a/b'), $this->advisory('c/d')], [['package' => 'a/old', 'replacement' => null]]), 5 * 86400);

        $status = $this->data('prod', true)->status('en');
        $rows = $this->conditionRows($this->data('prod', true));

        $this->assertSame('error', $status['status']);
        $this->assertSame(['key' => 'sulu_dashboard.system_attention', 'parameters' => ['count' => 4]], $status['headline']);
        $this->assertSame(['key' => 'sulu_dashboard.system_vulnerabilities_found', 'parameters' => ['count' => 2]], $rows['sulu_dashboard.system_vulnerabilities']['value']);
        $this->assertSame('error', $rows['sulu_dashboard.system_vulnerabilities']['severity']);
        $this->assertSame('warning', $rows['sulu_dashboard.system_packages']['severity']);
        $this->assertSame('error', $rows['sulu_dashboard.system_debug']['severity']);
        $this->assertSame('sulu_dashboard.system_debug_in_prod_hint', $rows['sulu_dashboard.system_debug']['text']);
        $this->assertSame('warning', $rows['sulu_dashboard.system_last_check']['severity'], 'five days are more than the 48 hours');
        $this->assertSame('sulu_dashboard.system_stale_hint', $rows['sulu_dashboard.system_last_check']['text']['key']);
    }

    public function testDebugModeIsRedInProductionOnlyAndTheLimitOfTheCheckIsConfigurable(): void
    {
        $this->save(AuditResult::ok(), 3 * 86400);

        $this->assertSame('ok', $this->conditionRows($this->data('dev', true))['sulu_dashboard.system_debug']['severity']);
        $this->assertSame('ok', $this->conditionRows($this->data('prod', false))['sulu_dashboard.system_debug']['severity']);
        $this->assertSame('ok', $this->conditionRows($this->data(maxAgeHours: 100))['sulu_dashboard.system_last_check']['severity']);
    }

    public function testAnAuditThatCouldNotRunIsAWarningWithItsReasonAndHasNoPackagesRow(): void
    {
        $this->save(AuditResult::unavailable(AuditResult::ERROR_TIMEOUT));

        $rows = $this->conditionRows($this->data());

        $this->assertSame('warning', $rows['sulu_dashboard.system_vulnerabilities']['severity']);
        $this->assertSame('sulu_dashboard.system_audit_error_timeout', $rows['sulu_dashboard.system_vulnerabilities']['text']);
        $this->assertArrayNotHasKey('sulu_dashboard.system_packages', $rows);
    }

    public function testThePhpSupportIsRedWhenItEndedAndSaysNothingForAnUnknownVersion(): void
    {
        $this->save(AuditResult::ok());

        $ended = $this->conditionRows($this->data(php: '8.1.30'))['sulu_dashboard.system_php_support'];
        $this->assertSame('error', $ended['severity']);
        $this->assertSame('sulu_dashboard.system_ended_on', $ended['value']['key']);
        $this->assertSame('Dec 31, 2025', $ended['value']['parameters']['date']);

        // NOW is 2027-01-15: 8.3 ends on 2027-12-31, within a year, but not within half a year
        $this->assertSame('ok', $this->conditionRows($this->data(php: '8.3.1'))['sulu_dashboard.system_php_support']['severity']);

        $this->assertArrayNotHasKey('sulu_dashboard.system_php_support', $this->conditionRows($this->data(php: '9.9.1')));
    }

    public function testMigrationsAndFailedMessagesAreWarningsWhenSomethingIsOpen(): void
    {
        $this->save(AuditResult::ok());
        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willReturn(2);
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $failed->method('count')->willReturn(3);

        $rows = $this->conditionRows($this->data(migrations: $migrations, failed: $failed));

        $this->assertSame(['key' => 'sulu_dashboard.system_migrations_open', 'parameters' => ['count' => 2]], $rows['sulu_dashboard.system_migrations']['value']);
        $this->assertSame('warning', $rows['sulu_dashboard.system_migrations']['severity']);
        $this->assertSame('sulu_dashboard.system_migrations_pending_hint', $rows['sulu_dashboard.system_migrations']['text']);
        $this->assertSame(['key' => 'sulu_dashboard.system_failed_count', 'parameters' => ['count' => 3]], $rows['sulu_dashboard.system_failed_messages']['value']);
        $this->assertSame('warning', $rows['sulu_dashboard.system_failed_messages']['severity']);
    }

    public function testAFailingCheckIsShownAndNeverSwallowedNorLeaksDetails(): void
    {
        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willThrowException(new RuntimeException('mysql://user:hunter2@db.internal/app'));
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $failed->method('count')->willThrowException(new RuntimeException('doctrine://default?queue=hunter2'));

        $rows = $this->conditionRows($this->data(migrations: $migrations, failed: $failed));

        $this->assertSame('sulu_dashboard.system_check_failed', $rows['sulu_dashboard.system_migrations']['value']);
        $this->assertSame('warning', $rows['sulu_dashboard.system_migrations']['severity']);
        $this->assertSame('sulu_dashboard.system_check_failed', $rows['sulu_dashboard.system_failed_messages']['value']);
        $this->assertStringNotContainsString('hunter2', (string) json_encode($rows));
    }

    public function testABigCacheOrLogIsAWarningRowOfTheCondition(): void
    {
        $this->store->save(new SystemCheck(self::NOW - 3600, AuditResult::ok(), [['path' => 'var/cache', 'bytes' => 2_000_000_000], ['path' => 'var/log', 'bytes' => 1000]]));

        $rows = $this->data()->status('en')['rows'];
        $hints = array_values(array_filter($rows, static fn (array $row): bool => \is_array($row['title']) && 'sulu_dashboard.system_storage_cache_large' === $row['title']['key']));

        $this->assertCount(1, $hints);
        $this->assertSame('warning', $hints[0]['severity']);
        $this->assertSame(['size' => '1.9 GB'], $hints[0]['title']['parameters']);
        $this->assertSame('sulu_dashboard.system_storage_cache_large_hint', $hints[0]['text']);
    }

    public function testVersionsAreShownAsPlainTextSoTheyAreNeverTakenForTranslationKeys(): void
    {
        $this->save(AuditResult::ok());

        $rows = $this->tile('versions')->getData('en')['rows'];

        $this->assertSame(['PHP', 'Symfony', 'Sulu'], array_column($rows, 'title'));
        $this->assertSame([['text' => '8.4.1'], ['text' => '7.4.2'], ['text' => '3.0.9']], array_column($rows, 'value'));
    }

    public function testVersionsNamePhpSupportInTheLanguageOfTheUser(): void
    {
        $en = $this->tile('versions')->getData('en')['rows'][0];
        $de = $this->tile('versions')->getData('de')['rows'][0];

        $this->assertSame('sulu_dashboard.system_php_until', $en['text']['key']);
        $this->assertSame('Dec 31, 2028', $en['text']['parameters']['date']);
        $this->assertSame('31.12.2028', $de['text']['parameters']['date']);
        $this->assertNull($en['severity']);

        $old = $this->tile('versions', $this->data(php: '8.1.1'))->getData('en')['rows'][0];
        $this->assertSame('error', $old['severity']);
        $this->assertSame('sulu_dashboard.system_php_ended_on', $old['text']['key']);
    }

    public function testVersionsShowANewerPatchReleaseFromTheLastCheck(): void
    {
        $this->store->save(new SystemCheck(self::NOW - 3600, AuditResult::ok(), [], null, null, [['package' => 'sulu/sulu', 'installed' => '3.0.9', 'latest' => '3.0.10']]));

        $rows = $this->tile('versions')->getData('en')['rows'];

        $this->assertNull($rows[1]['severity'], 'Symfony is up to date');
        $this->assertSame('warning', $rows[2]['severity']);
        $this->assertSame(['key' => 'sulu_dashboard.system_update_available', 'parameters' => ['version' => '3.0.10']], $rows[2]['text']);
    }

    public function testEnvironmentListsTheLiveValuesAndNothingSecret(): void
    {
        $runtime = new RuntimeInfo('512M', 60, true, 'Europe/Berlin', 'de');

        $rows = $this->tile('environment', $this->data('prod', false, runtime: $runtime))->getData('en')['rows'];

        $this->assertSame(
            ['sulu_dashboard.system_mode', 'sulu_dashboard.system_debug', 'sulu_dashboard.system_memory_limit', 'sulu_dashboard.system_max_execution_time', 'OPcache', 'sulu_dashboard.system_timezone', 'sulu_dashboard.system_locale'],
            array_column($rows, 'title'),
        );
        $this->assertSame(
            [['text' => 'prod'], 'sulu_dashboard.system_off', ['text' => '512M'], ['key' => 'sulu_dashboard.system_seconds', 'parameters' => ['count' => 60]], 'sulu_dashboard.system_on', ['text' => 'Europe/Berlin'], ['text' => 'de']],
            array_column($rows, 'value'),
        );
        $this->assertSame([null], array_unique(array_column($rows, 'severity')), 'without a problem there is no icon');
    }

    public function testEnvironmentWarnsAboutDebugModeAndAMissingOpcacheInProductionOnly(): void
    {
        $runtime = new RuntimeInfo('-1', 0, false, 'UTC', 'en');

        $prod = $this->tile('environment', $this->data('prod', true, runtime: $runtime))->getData('en')['rows'];
        $this->assertSame('error', $prod[1]['severity']);
        $this->assertSame('sulu_dashboard.system_debug_in_prod_hint', $prod[1]['text']);
        $this->assertSame('sulu_dashboard.system_unlimited', $prod[2]['value']);
        $this->assertSame('sulu_dashboard.system_unlimited', $prod[3]['value']);
        $this->assertSame('warning', $prod[4]['severity']);
        $this->assertSame('sulu_dashboard.system_opcache_hint', $prod[4]['text']);

        $dev = $this->tile('environment', $this->data('dev', true, runtime: $runtime))->getData('en')['rows'];
        $this->assertNull($dev[1]['severity']);
        $this->assertNull($dev[4]['severity']);
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
