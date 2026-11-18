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
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
        return new SystemWidget(
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

    private function save(AuditResult $audit, int $age = 3600): void
    {
        $this->store->save(new SystemCheck(self::NOW - $age, $audit, [['path' => 'public/uploads', 'bytes' => 1_610_612_736], ['path' => 'var', 'bytes' => 300]]));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function rowsWith(array $data, string $key): array
    {
        return array_values(array_filter($data['rows'], static fn (array $row): bool => $row['title'] === $key || (\is_array($row['title']) && ($row['title']['key'] ?? null) === $key)));
    }

    public function testIsAListTileOnlyForUsersWithTheSystemPermission(): void
    {
        $widget = $this->widget();

        $this->assertSame('sulu_system', $widget->getKey());
        $this->assertSame('list', $widget->getType());
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

    public function testShowsTheVersionsAsPlainTextSoTheyAreNeverTakenForTranslationKeys(): void
    {
        $this->save(AuditResult::ok());
        $data = $this->widget()->getData('en');

        $this->assertSame(['text' => '8.4.1'], $this->rowsWith($data, 'PHP')[0]['meta']);
        $this->assertSame(['text' => '7.4.2'], $this->rowsWith($data, 'Symfony')[0]['meta']);
        $this->assertSame(['text' => '3.0.9'], $this->rowsWith($data, 'Sulu')[0]['meta']);
        $this->assertSame(['text' => 'prod'], $this->rowsWith($data, 'sulu_dashboard.system_environment')[0]['meta']);
    }

    public function testWarnsClearlyAboutDebugModeInProduction(): void
    {
        $this->save(AuditResult::ok());
        $data = $this->widget('prod', true)->getData('en');

        $first = $data['rows'][0];
        $this->assertSame('sulu_dashboard.system_debug_in_prod', $first['title']);
        $this->assertSame('error', $first['severity']);
        $this->assertSame('sulu_dashboard.system_on', $this->rowsWith($data, 'sulu_dashboard.system_debug')[0]['meta']);
    }

    public function testNoWarningForDebugOutsideProduction(): void
    {
        $this->save(AuditResult::ok());

        $this->assertSame([], $this->rowsWith($this->widget('dev', true)->getData('en'), 'sulu_dashboard.system_debug_in_prod'));
        $this->assertSame([], $this->rowsWith($this->widget('prod', false)->getData('en'), 'sulu_dashboard.system_debug_in_prod'));
    }

    public function testWithoutAResultTheTileSaysThatTheCheckNeverRan(): void
    {
        $data = $this->widget()->getData('en');

        $rows = $this->rowsWith($data, 'sulu_dashboard.system_not_run');
        $this->assertCount(1, $rows);
        $this->assertSame('warning', $rows[0]['severity']);
        $this->assertSame([], $this->rowsWith($data, 'sulu_dashboard.system_audit'));
        $this->assertSame([], $this->rowsWith($data, 'public/uploads'));
        // the cheap facts are still there
        $this->assertNotEmpty($this->rowsWith($data, 'PHP'));
    }

    public function testShowsTheAgeOfTheCheckAndTheSizeOfTheFolders(): void
    {
        $this->save(AuditResult::ok(), 7200);
        $data = $this->widget()->getData('de');

        $this->assertSame([], $this->rowsWith($data, 'sulu_dashboard.system_not_run'));
        $this->assertSame(['key' => 'sulu_dashboard.system_age_hours', 'parameters' => ['count' => 2]], $this->rowsWith($data, 'sulu_dashboard.system_last_check')[0]['meta']);
        $this->assertSame(['text' => '1,5 GB'], $this->rowByText($data, 'public/uploads')['meta']);
        $this->assertSame(['text' => '300 B'], $this->rowByText($data, 'var')['meta']);
        $this->assertSame('sulu_dashboard.system_audit_ok', $this->rowsWith($data, 'sulu_dashboard.system_audit')[0]['meta']);
    }

    public function testAnOldCheckIsReportedBecauseTheCronJobMayHaveStopped(): void
    {
        $this->save(AuditResult::ok(), 3 * 86400);
        $data = $this->widget()->getData('en');

        $stale = $this->rowsWith($data, 'sulu_dashboard.system_stale');
        $this->assertCount(1, $stale);
        $this->assertSame('warning', $stale[0]['severity']);
        $this->assertSame(['key' => 'sulu_dashboard.system_age_days', 'parameters' => ['count' => 3]], $stale[0]['text']);

        // the limit is configurable
        $this->assertSame([], $this->rowsWith($this->widget(maxAgeHours: 100)->getData('en'), 'sulu_dashboard.system_stale'));
    }

    public function testVulnerabilitiesAreAnErrorAndListTheAffectedPackages(): void
    {
        $this->save(AuditResult::vulnerable([
            ['package' => 'vendor/one', 'title' => 'Remote code execution', 'cve' => 'CVE-2026-1'],
            ['package' => 'vendor/two', 'title' => 'XSS', 'cve' => null],
        ]));
        $data = $this->widget()->getData('en');

        $first = $data['rows'][0];
        $this->assertSame('error', $first['severity']);
        $this->assertSame(['key' => 'sulu_dashboard.system_audit_found', 'parameters' => ['count' => 2]], $first['title']);
        $this->assertSame(
            [['title' => ['text' => 'vendor/one: Remote code execution (CVE-2026-1)']], ['title' => ['text' => 'vendor/two: XSS']]],
            $data['footer']['rows'],
        );
    }

    public function testManyVulnerabilitiesAreCutAfterTenWithAHint(): void
    {
        $advisories = [];
        for ($i = 1; $i <= 13; ++$i) {
            $advisories[] = ['package' => 'vendor/p' . $i, 'title' => 'Bug', 'cve' => null];
        }
        $this->save(AuditResult::vulnerable($advisories));

        $rows = $this->widget()->getData('en')['footer']['rows'];

        $this->assertCount(11, $rows);
        $this->assertSame(['key' => 'sulu_dashboard.system_audit_more', 'parameters' => ['count' => 3]], $rows[10]['title']);
    }

    public function testAnAuditThatCouldNotRunIsAWarningWithItsReason(): void
    {
        foreach ([AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_FAILED, AuditResult::ERROR_TIMEOUT, 'something else'] as $error) {
            $this->save(AuditResult::unavailable($error));
            $rows = $this->rowsWith($this->widget()->getData('en'), 'sulu_dashboard.system_audit_unavailable');

            $this->assertCount(1, $rows, $error);
            $this->assertSame('warning', $rows[0]['severity']);
            $expected = \in_array($error, [AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_TIMEOUT], true) ? $error : AuditResult::ERROR_FAILED;
            $this->assertSame('sulu_dashboard.system_audit_error_' . $expected, $rows[0]['text']);
        }
    }

    public function testOpenMigrationsAreOnlyCheckedWhenAvailable(): void
    {
        $this->save(AuditResult::ok());

        $this->assertSame([], $this->rowsWith($this->widget()->getData('en'), 'sulu_dashboard.system_migrations'));

        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willReturn(2);
        $rows = $this->rowsWith($this->widget(migrations: $migrations)->getData('en'), 'sulu_dashboard.system_migrations_pending');
        $this->assertCount(1, $rows);
        $this->assertSame(['count' => 2], $rows[0]['title']['parameters']);

        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willReturn(0);
        $this->assertSame('sulu_dashboard.system_migrations_ok', $this->rowsWith($this->widget(migrations: $migrations)->getData('en'), 'sulu_dashboard.system_migrations')[0]['meta']);
    }

    public function testAMigrationCheckThatFailsIsShownAndNotSwallowed(): void
    {
        $this->save(AuditResult::ok());
        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willThrowException(new RuntimeException('mysql://user:secret@host/db'));

        $data = $this->widget(migrations: $migrations)->getData('en');

        $this->assertCount(1, $this->rowsWith($data, 'sulu_dashboard.system_migrations_error'));
        $this->assertStringNotContainsString('secret', (string) json_encode($data));
    }

    public function testFailedMessagesAreOnlyShownWithAFailureTransport(): void
    {
        $this->save(AuditResult::ok());

        $this->assertSame([], $this->rowsWith($this->widget()->getData('en'), 'sulu_dashboard.system_failed_messages'));

        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $failed->method('count')->willReturn(3);
        $rows = $this->rowsWith($this->widget(failed: $failed)->getData('en'), 'sulu_dashboard.system_failed_found');
        $this->assertCount(1, $rows);
        $this->assertSame(['count' => 3], $rows[0]['title']['parameters']);

        $none = $this->createMock(FailedMessages::class);
        $none->method('isConfigured')->willReturn(true);
        $none->method('count')->willReturn(0);
        $this->assertSame('sulu_dashboard.system_failed_none', $this->rowsWith($this->widget(failed: $none)->getData('en'), 'sulu_dashboard.system_failed_messages')[0]['meta']);

        $unknown = $this->createMock(FailedMessages::class);
        $unknown->method('isConfigured')->willReturn(true);
        $unknown->method('count')->willReturn(null);
        $this->assertCount(1, $this->rowsWith($this->widget(failed: $unknown)->getData('en'), 'sulu_dashboard.system_failed_error'));
    }

    public function testErrorsComeBeforeWarningsBeforeFacts(): void
    {
        $this->save(AuditResult::vulnerable([['package' => 'a/b', 'title' => 'x', 'cve' => null]]), 5 * 86400);
        $rows = $this->widget('prod', true)->getData('en')['rows'];

        $severities = array_map(static fn (array $row): string => $row['severity'] ?? 'fact', $rows);
        $this->assertSame(['error', 'error', 'warning'], \array_slice($severities, 0, 3));
        $this->assertSame('fact', $severities[3]);
        $sorted = $severities;
        usort($sorted, static fn (string $a, string $b): int => ['error' => 0, 'warning' => 1, 'fact' => 2][$a] <=> ['error' => 0, 'warning' => 1, 'fact' => 2][$b]);
        $this->assertSame($sorted, $severities);
    }

    public function testShowsNoSecretsAndNoAbsolutePaths(): void
    {
        $this->save(AuditResult::vulnerable([['package' => 'a/b', 'title' => 'x', 'cve' => null]]));
        $migrations = $this->createMock(PendingMigrations::class);
        $migrations->method('isAvailable')->willReturn(true);
        $migrations->method('count')->willThrowException(new RuntimeException('SQLSTATE: mysql://root:hunter2@db.internal:3306/app in /var/www/secret/project'));
        $failed = $this->createMock(FailedMessages::class);
        $failed->method('isConfigured')->willReturn(true);
        $failed->method('count')->willThrowException(new RuntimeException('doctrine://default?queue=hunter2 /home/user/app'));

        $json = (string) json_encode($this->widget('prod', true, $migrations, $failed)->getData('en'));

        foreach (['hunter2', 'mysql://', 'doctrine://', '/var/www', '/home/', 'secret', 'internal', 'DATABASE_URL', 'APP_SECRET', '\\\\'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, $forbidden);
        }
        // folders only by their relative name
        $this->assertStringContainsString('public\/uploads', $json);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function rowByText(array $data, string $text): array
    {
        foreach ($data['rows'] as $row) {
            if (\is_array($row['title']) && ($row['title']['text'] ?? null) === $text) {
                return $row;
            }
        }

        $this->fail('No row ' . $text);
    }
}
