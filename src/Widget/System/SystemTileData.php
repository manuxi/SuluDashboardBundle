<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\System;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\FailedMessages;
use Manuxi\SuluDashboardBundle\System\PendingMigrations;
use Manuxi\SuluDashboardBundle\System\PhpSupport;
use Manuxi\SuluDashboardBundle\System\RuntimeInfo;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use Manuxi\SuluDashboardBundle\System\SystemVersions;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The data of the tab "System" (one method per tile).
 *
 * Nothing slow runs here: the security check, the newer patch releases and the folder sizes are made by the console
 * command (sulu:dashboard:system-check, cron) and read from the cache pool; versions, the environment, the open
 * migrations and the failed messages are cheap. Nothing secret is shown: no connection strings, keys or absolute paths,
 * only versions, counts, package names, relative folder names and a few PHP settings.
 *
 * The tiles "Condition", "Versions" and "Environment" hand over rows {title, value?, text?, severity?} for one component
 * (widgets/SystemFacts.js). Texts are admin translation keys, {key, parameters} or {text} (shown as it is): see
 * utils.js tx().
 */
final class SystemTileData
{
    public const COMMAND = 'php bin/adminconsole sulu:dashboard:system-check';

    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_OK = 'ok';

    /** how the table of vulnerabilities is sorted: the worst first, unknown severities last */
    private const SEVERITY_ORDER = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    public function __construct(
        private readonly SystemVersions $versions,
        private readonly string $environment,
        private readonly bool $debug,
        private readonly SystemCheckStore $store,
        private readonly PendingMigrations $migrations,
        private readonly FailedMessages $failedMessages,
        private readonly int $maxAgeHours = 48,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?Closure $now = null,
        private readonly ?StorageReport $storageReport = null,
        private readonly ?RuntimeInfo $runtime = null,
    ) {
    }

    /**
     * Tile "Security vulnerabilities": the advisories of "composer audit" and the abandoned packages.
     *
     * status: ok | vulnerable | unavailable (the audit could not run, "reason" says why) | never (no result yet).
     *
     * @return array<string, mixed>
     */
    public function vulnerabilities(): array
    {
        $check = $this->store->load();
        $data = ['status' => 'never', 'advisories' => [], 'abandoned' => [], 'reason' => null, 'detail' => null, 'command' => self::COMMAND];
        if (null === $check) {
            return $data;
        }

        $audit = $check->audit;
        $data['status'] = $audit->status;
        $data['abandoned'] = $audit->abandoned;

        if (AuditResult::VULNERABLE === $audit->status) {
            $advisories = $audit->advisories;
            usort($advisories, static fn (array $a, array $b): int => [self::SEVERITY_ORDER[$a['severity'] ?? ''] ?? 99, $a['package']] <=> [self::SEVERITY_ORDER[$b['severity'] ?? ''] ?? 99, $b['package']]);
            $data['advisories'] = $advisories;
        } elseif (AuditResult::UNAVAILABLE === $audit->status) {
            $known = [AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_TIMEOUT, AuditResult::ERROR_NETWORK];
            $data['reason'] = 'sulu_dashboard.system_audit_error_' . (\in_array($audit->error, $known, true) ? $audit->error : AuditResult::ERROR_FAILED);
            $data['detail'] = $audit->detail;
        }

        return $data;
    }

    /**
     * Tile "Condition": whether anything needs attention (the headline, the worst severity), and below it one row per
     * check with its result: vulnerabilities, abandoned packages, PHP support, debug mode, open migrations, failed
     * messages, big cache or logs, and the age of the last background check.
     *
     * status: ok | warning | error. The rows are in the order they are checked; a check that does not apply here (no
     * migrations bundle, no failure transport) has no row.
     *
     * @return array<string, mixed>
     */
    public function status(string $locale = 'en'): array
    {
        $check = $this->store->load();
        $rows = [$this->vulnerabilityRow($check)];

        if (null !== $check && AuditResult::UNAVAILABLE !== $check->audit->status) {
            $rows[] = $this->packagesRow($check);
        }

        $support = PhpSupport::check($this->versions->php, $this->date());
        if (PhpSupport::STATE_UNKNOWN !== $support['state']) {
            $rows[] = $this->phpSupportRow($support, $locale);
        }

        $rows[] = $this->debugRow();

        foreach ([$this->migrationRow(), $this->failedRow()] as $row) {
            if (null !== $row) {
                $rows[] = $row;
            }
        }

        if (null !== $check) {
            foreach (($this->storageReport ?? new StorageReport())->hints($check, $locale) as $hint) {
                $rows[] = $this->row(self::SEVERITY_WARNING, $hint['title'], null, $hint['text']);
            }
        }

        $rows[] = $this->lastCheckRow($check);

        $errors = \count(array_filter($rows, static fn (array $row): bool => self::SEVERITY_ERROR === $row['severity']));
        $warnings = \count(array_filter($rows, static fn (array $row): bool => self::SEVERITY_WARNING === $row['severity']));

        return [
            'status' => $errors > 0 ? self::SEVERITY_ERROR : ($warnings > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK),
            'headline' => $errors + $warnings > 0
                ? ['key' => 'sulu_dashboard.system_attention', 'parameters' => ['count' => $errors + $warnings]]
                : 'sulu_dashboard.system_all_good',
            'rows' => $rows,
        ];
    }

    /**
     * Tile "Versions": PHP (with the end of its security support), Symfony and Sulu. If the last background check found a
     * newer patch release of Symfony or Sulu, the row says so.
     *
     * @return array<string, mixed>
     */
    public function versions(string $locale = 'en'): array
    {
        $support = PhpSupport::check($this->versions->php, $this->date());
        $note = match ($support['state']) {
            PhpSupport::STATE_OK => [null, $this->supportText('sulu_dashboard.system_php_until', $support['until'], $locale)],
            PhpSupport::STATE_ENDING => [self::SEVERITY_WARNING, $this->supportText('sulu_dashboard.system_php_until', $support['until'], $locale)],
            PhpSupport::STATE_ENDED => [self::SEVERITY_ERROR, null === $support['until'] ? 'sulu_dashboard.system_php_ended' : $this->supportText('sulu_dashboard.system_php_ended_on', $support['until'], $locale)],
            default => [null, null],
        };

        $rows = [$this->row($note[0], 'PHP', $this->text($this->versions->php), $note[1])];

        $rows[] = $this->versionRow('Symfony', $this->versions->symfony, 'symfony/framework-bundle');
        if (null !== $this->versions->sulu) {
            $rows[] = $this->versionRow('Sulu', $this->versions->sulu, 'sulu/sulu');
        }

        return ['rows' => $rows];
    }

    /**
     * Tile "Environment": mode, debug mode (red in production), the PHP limits, OPcache (a warning in production if it
     * is off), time zone and default language.
     *
     * @return array<string, mixed>
     */
    public function environment(): array
    {
        $runtime = $this->runtime ?? RuntimeInfo::detect();

        $memory = '-1' === trim($runtime->memoryLimit) ? 'sulu_dashboard.system_unlimited' : $this->text($runtime->memoryLimit);
        $time = $runtime->maxExecutionTime > 0
            ? ['key' => 'sulu_dashboard.system_seconds', 'parameters' => ['count' => $runtime->maxExecutionTime]]
            : 'sulu_dashboard.system_unlimited';

        $opcacheProblem = !$runtime->opcache && 'prod' === $this->environment;

        return ['rows' => [
            $this->row(null, 'sulu_dashboard.system_mode', $this->text($this->environment)),
            $this->debugRow(false),
            $this->row(null, 'sulu_dashboard.system_memory_limit', $memory),
            $this->row(null, 'sulu_dashboard.system_max_execution_time', $time),
            $this->row($opcacheProblem ? self::SEVERITY_WARNING : null, 'OPcache', $runtime->opcache ? 'sulu_dashboard.system_on' : 'sulu_dashboard.system_off', $opcacheProblem ? 'sulu_dashboard.system_opcache_hint' : null),
            $this->row(null, 'sulu_dashboard.system_timezone', $this->text($runtime->timezone)),
            $this->row(null, 'sulu_dashboard.system_locale', $this->text($runtime->locale)),
        ]];
    }

    /**
     * Tile "Storage": what the folders hold, the change since the measurement before, the media and hints. Measured by
     * the console command; without a result the tile says how to get one.
     *
     * @return array<string, mixed>
     */
    public function storage(string $locale): array
    {
        $check = $this->store->load();
        if (null === $check || [] === $check->storage) {
            return ['status' => 'never', 'command' => self::COMMAND];
        }

        return ($this->storageReport ?? new StorageReport())->build($check, $locale, $this->time(), $this->isStale($check));
    }

    /**
     * @return array<string, mixed>
     */
    private function vulnerabilityRow(?SystemCheck $check): array
    {
        $title = 'sulu_dashboard.system_vulnerabilities';

        if (null === $check) {
            return $this->row(self::SEVERITY_WARNING, $title, 'sulu_dashboard.system_not_checked');
        }

        $audit = $check->audit;

        if (AuditResult::VULNERABLE === $audit->status) {
            return $this->row(self::SEVERITY_ERROR, $title, ['key' => 'sulu_dashboard.system_vulnerabilities_found', 'parameters' => ['count' => \count($audit->advisories)]], 'sulu_dashboard.system_vulnerabilities_found_hint');
        }

        if (AuditResult::UNAVAILABLE === $audit->status) {
            $known = [AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_TIMEOUT, AuditResult::ERROR_NETWORK];

            return $this->row(self::SEVERITY_WARNING, $title, 'sulu_dashboard.system_audit_unavailable', 'sulu_dashboard.system_audit_error_' . (\in_array($audit->error, $known, true) ? $audit->error : AuditResult::ERROR_FAILED));
        }

        return $this->row(self::SEVERITY_OK, $title, 'sulu_dashboard.system_none_known');
    }

    /**
     * @return array<string, mixed>
     */
    private function packagesRow(SystemCheck $check): array
    {
        $abandoned = \count($check->audit->abandoned);

        return $abandoned > 0
            ? $this->row(self::SEVERITY_WARNING, 'sulu_dashboard.system_packages', ['key' => 'sulu_dashboard.system_packages_abandoned', 'parameters' => ['count' => $abandoned]], 'sulu_dashboard.system_abandoned_hint_short')
            : $this->row(self::SEVERITY_OK, 'sulu_dashboard.system_packages', 'sulu_dashboard.system_packages_ok');
    }

    /**
     * @param array{state: string, until: ?string} $support
     *
     * @return array<string, mixed>
     */
    private function phpSupportRow(array $support, string $locale): array
    {
        $title = 'sulu_dashboard.system_php_support';
        $until = null === $support['until'] ? null : ['key' => 'sulu_dashboard.system_until', 'parameters' => ['date' => $this->formatDate($support['until'], $locale)]];

        return match ($support['state']) {
            PhpSupport::STATE_ENDED => $this->row(self::SEVERITY_ERROR, $title, null === $support['until'] ? 'sulu_dashboard.system_ended' : ['key' => 'sulu_dashboard.system_ended_on', 'parameters' => ['date' => $this->formatDate($support['until'], $locale)]], 'sulu_dashboard.system_php_ended_hint'),
            PhpSupport::STATE_ENDING => $this->row(self::SEVERITY_WARNING, $title, $until, 'sulu_dashboard.system_php_ending_hint'),
            default => $this->row(self::SEVERITY_OK, $title, $until),
        };
    }

    /**
     * Debug mode: red with a hint in production (visitors could see internal details), otherwise just the state.
     *
     * @return array<string, mixed>
     */
    private function debugRow(bool $withOk = true): array
    {
        $problem = $this->debug && 'prod' === $this->environment;
        $value = $this->debug ? 'sulu_dashboard.system_on' : 'sulu_dashboard.system_off';

        if ($problem) {
            return $this->row(self::SEVERITY_ERROR, 'sulu_dashboard.system_debug', $value, 'sulu_dashboard.system_debug_in_prod_hint');
        }

        return $this->row($withOk ? self::SEVERITY_OK : null, 'sulu_dashboard.system_debug', $value);
    }

    /**
     * @return array<string, mixed>|null null: the check is not available (no migrations bundle)
     */
    private function migrationRow(): ?array
    {
        if (!$this->migrations->isAvailable()) {
            return null;
        }

        $title = 'sulu_dashboard.system_migrations';

        try {
            $open = $this->migrations->count();
        } catch (Throwable $exception) {
            $this->logger?->warning('Dashboard system tile: the open migrations could not be read.', ['exception' => $exception]);

            return $this->row(self::SEVERITY_WARNING, $title, 'sulu_dashboard.system_check_failed');
        }

        return $open > 0
            ? $this->row(self::SEVERITY_WARNING, $title, ['key' => 'sulu_dashboard.system_migrations_open', 'parameters' => ['count' => $open]], 'sulu_dashboard.system_migrations_pending_hint')
            : $this->row(self::SEVERITY_OK, $title, 'sulu_dashboard.system_migrations_done');
    }

    /**
     * @return array<string, mixed>|null null: no failure transport
     */
    private function failedRow(): ?array
    {
        if (!$this->failedMessages->isConfigured()) {
            return null;
        }

        $title = 'sulu_dashboard.system_failed_messages';

        try {
            $failed = $this->failedMessages->count();
        } catch (Throwable $exception) {
            $this->logger?->warning('Dashboard system tile: the failed messages could not be counted.', ['exception' => $exception]);

            return $this->row(self::SEVERITY_WARNING, $title, 'sulu_dashboard.system_check_failed');
        }

        if (null === $failed) {
            return $this->row(self::SEVERITY_WARNING, $title, 'sulu_dashboard.system_check_failed');
        }

        return $failed > 0
            ? $this->row(self::SEVERITY_WARNING, $title, ['key' => 'sulu_dashboard.system_failed_count', 'parameters' => ['count' => $failed]], 'sulu_dashboard.system_failed_found_hint')
            : $this->row(self::SEVERITY_OK, $title, 'sulu_dashboard.system_failed_none');
    }

    /**
     * How old the result of the console command is, a warning if it never ran or is too old, always with the command.
     *
     * @return array<string, mixed>
     */
    private function lastCheckRow(?SystemCheck $check): array
    {
        $title = 'sulu_dashboard.system_last_check';
        $parameters = ['command' => self::COMMAND];

        if (null === $check) {
            return $this->row(self::SEVERITY_WARNING, $title, 'sulu_dashboard.system_never', ['key' => 'sulu_dashboard.system_not_run_hint', 'parameters' => $parameters]);
        }

        $age = max(0, $this->time() - $check->checkedAt);

        return $this->isStale($check)
            ? $this->row(self::SEVERITY_WARNING, $title, self::ageText($age), ['key' => 'sulu_dashboard.system_stale_hint', 'parameters' => $parameters])
            : $this->row(self::SEVERITY_OK, $title, self::ageText($age), ['key' => 'sulu_dashboard.system_refresh_hint', 'parameters' => $parameters]);
    }

    /**
     * Symfony or Sulu: the version, and a hint if the last background check found a newer patch release.
     *
     * @return array<string, mixed>
     */
    private function versionRow(string $name, string $version, string $package): array
    {
        $latest = null;
        foreach ($this->store->load()->updates ?? [] as $update) {
            if ($update['package'] === $package) {
                $latest = $update['latest'];
            }
        }

        if (null === $latest) {
            return $this->row(null, $name, $this->text($version));
        }

        return $this->row(self::SEVERITY_WARNING, $name, $this->text($version), ['key' => 'sulu_dashboard.system_update_available', 'parameters' => ['version' => $latest]]);
    }

    private function isStale(SystemCheck $check): bool
    {
        return max(0, $this->time() - $check->checkedAt) > $this->maxAgeHours * 3600;
    }

    private function time(): int
    {
        return null === $this->now ? time() : (int) ($this->now)();
    }

    private function date(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . $this->time());
    }

    /**
     * "2028-12-31" as the day in the language of the user ("31 Dec 2028"); the day itself, whatever the time zone.
     */
    private function formatDate(string $day, string $locale): string
    {
        if (!class_exists(IntlDateFormatter::class)) {
            return $day;
        }

        $date = new DateTimeImmutable($day . ' 12:00:00', new DateTimeZone('UTC'));
        $formatted = (new IntlDateFormatter($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, 'UTC'))->format($date);

        return false === $formatted ? $day : $formatted;
    }

    /**
     * @return array{key: string, parameters: array{date: string}}|null
     */
    private function supportText(string $key, ?string $until, string $locale): ?array
    {
        return null === $until ? null : ['key' => $key, 'parameters' => ['date' => $this->formatDate($until, $locale)]];
    }

    /**
     * A text that is shown as it is: never taken for a translation key ("8.4.27" looks like one).
     *
     * @return array{text: string}
     */
    private function text(string $value): array
    {
        return ['text' => $value];
    }

    /**
     * One row of a tile of facts.
     *
     * @param string|array<string, mixed>      $title
     * @param string|array<string, mixed>|null $value
     * @param string|array<string, mixed>|null $text  grey second line
     *
     * @return array<string, mixed>
     */
    private function row(?string $severity, string|array $title, string|array|null $value = null, string|array|null $text = null): array
    {
        return ['title' => $title, 'value' => $value, 'text' => $text, 'severity' => $severity];
    }

    /**
     * @return array{key: string, parameters: array{count: int}}
     */
    public static function ageText(int $seconds): array
    {
        if ($seconds < 3600) {
            return ['key' => 'sulu_dashboard.system_age_minutes', 'parameters' => ['count' => max(1, intdiv($seconds, 60))]];
        }

        if ($seconds < 172800) {
            return ['key' => 'sulu_dashboard.system_age_hours', 'parameters' => ['count' => intdiv($seconds, 3600)]];
        }

        return ['key' => 'sulu_dashboard.system_age_days', 'parameters' => ['count' => intdiv($seconds, 86400)]];
    }
}
