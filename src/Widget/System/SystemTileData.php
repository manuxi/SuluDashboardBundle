<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\System;

use Closure;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\FailedMessages;
use Manuxi\SuluDashboardBundle\System\PendingMigrations;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use Manuxi\SuluDashboardBundle\System\SystemVersions;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The data of the tab "System" (one method per tile) and of the small tile on the overview that sums it up.
 *
 * Nothing slow runs here: the security check and the folder sizes are made by the console command
 * (sulu:dashboard:system-check, cron) and read from the cache pool; versions, the environment, the open migrations and
 * the failed messages are cheap. Nothing secret is shown: no connection strings, keys or absolute paths, only versions,
 * counts, package names and relative folder names.
 *
 * Texts are admin translation keys, {key, parameters} or {text} (shown as it is): see utils.js tx().
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
    ) {
    }

    public function hasMigrations(): bool
    {
        return $this->migrations->isAvailable();
    }

    public function hasFailedMessages(): bool
    {
        return $this->failedMessages->isConfigured();
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
        $data = ['status' => 'never', 'advisories' => [], 'abandoned' => [], 'reason' => null, 'command' => self::COMMAND];
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
            $known = [AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_TIMEOUT];
            $data['reason'] = 'sulu_dashboard.system_audit_error_' . (\in_array($audit->error, $known, true) ? $audit->error : AuditResult::ERROR_FAILED);
        }

        return $data;
    }

    /**
     * Tile "Versions": PHP, Symfony, Sulu.
     *
     * @return array<string, mixed>
     */
    public function versions(): array
    {
        $rows = [$this->fact('PHP', $this->text($this->versions->php)), $this->fact('Symfony', $this->text($this->versions->symfony))];
        if (null !== $this->versions->sulu) {
            $rows[] = $this->fact('Sulu', $this->text($this->versions->sulu));
        }

        return ['header' => false, 'rows' => $rows];
    }

    /**
     * Tile "Environment": the mode, the debug switch, and a clear warning for debug mode in production.
     *
     * @return array<string, mixed>
     */
    public function environment(): array
    {
        $rows = [];
        if ($this->debugInProduction()) {
            $rows[] = $this->problem(self::SEVERITY_ERROR, 'sulu_dashboard.system_debug_in_prod', 'sulu_dashboard.system_debug_in_prod_hint');
        }

        $rows[] = $this->fact('sulu_dashboard.system_mode', $this->text($this->environment));
        $rows[] = $this->fact('sulu_dashboard.system_debug', $this->debug ? 'sulu_dashboard.system_on' : 'sulu_dashboard.system_off');

        return ['header' => false, 'rows' => $rows];
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
     * Tile "Database migrations".
     *
     * @return array<string, mixed>
     */
    public function migrations(): array
    {
        $problem = $this->migrationProblem();

        return ['header' => false, 'rows' => [$problem ?? $this->problem(self::SEVERITY_OK, 'sulu_dashboard.system_migrations_ok')]];
    }

    /**
     * Tile "Failed messages".
     *
     * @return array<string, mixed>
     */
    public function failedMessages(): array
    {
        $problem = $this->failedProblem();

        return ['header' => false, 'rows' => [$problem ?? $this->problem(self::SEVERITY_OK, 'sulu_dashboard.system_failed_none')]];
    }

    /**
     * Tile "Last check": how old the result of the console command is, a warning if it never ran or is too old, always
     * with the command as text.
     *
     * @return array<string, mixed>
     */
    public function lastCheck(): array
    {
        return ['header' => false, 'rows' => [$this->lastCheckRow()]];
    }

    /**
     * The small tile of the overview: a traffic light. Every problem is a row with its severity, without a problem one
     * green row; below it the age of the check and a link to the tab.
     *
     * @return array<string, mixed>
     */
    public function summary(string $locale = 'en'): array
    {
        $rows = $this->problems($locale);
        if ([] === $rows) {
            $rows[] = $this->problem(self::SEVERITY_OK, 'sulu_dashboard.system_all_good');
        }

        $age = $this->age();
        $rows[] = $this->fact('sulu_dashboard.system_last_check', null === $age ? 'sulu_dashboard.system_never' : $this->ageText($age));

        return [
            'header' => false,
            'limit' => 10,
            'rows' => $rows,
            'footer' => ['rows' => [['title' => 'sulu_dashboard.system_details', 'icon' => 'su-arrow-right', 'link' => ['view' => DashboardAdmin::TAB_VIEW_PREFIX . SystemTabProvider::KEY, 'attributes' => (object) []]]]],
        ];
    }

    /**
     * Everything that needs attention, the worst first (errors, then warnings). Used by the overview tile.
     *
     * @return list<array<string, mixed>>
     */
    private function problems(string $locale): array
    {
        $errors = [];
        $warnings = [];

        if ($this->debugInProduction()) {
            $errors[] = $this->problem(self::SEVERITY_ERROR, 'sulu_dashboard.system_debug_in_prod');
        }

        $check = $this->store->load();
        if (null === $check) {
            $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_not_run');
        } else {
            $audit = $check->audit;
            if (AuditResult::VULNERABLE === $audit->status) {
                $errors[] = $this->problem(self::SEVERITY_ERROR, ['key' => 'sulu_dashboard.system_audit_found', 'parameters' => ['count' => \count($audit->advisories)]]);
            } elseif (AuditResult::UNAVAILABLE === $audit->status) {
                $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_audit_unavailable');
            }

            if ([] !== $audit->abandoned) {
                $warnings[] = $this->problem(self::SEVERITY_WARNING, ['key' => 'sulu_dashboard.system_abandoned_found', 'parameters' => ['count' => \count($audit->abandoned)]]);
            }

            if ($this->isStale($check)) {
                $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_stale');
            }

            foreach (($this->storageReport ?? new StorageReport())->hints($check, $locale) as $hint) {
                $warnings[] = $this->problem(self::SEVERITY_WARNING, $hint['title'], $hint['text']);
            }
        }

        foreach ([$this->migrationProblem(), $this->failedProblem()] as $problem) {
            if (null !== $problem) {
                $warnings[] = $problem;
            }
        }

        return [...$errors, ...$warnings];
    }

    private function debugInProduction(): bool
    {
        return $this->debug && 'prod' === $this->environment;
    }

    /**
     * @return array<string, mixed>|null null: all migrations ran, or the check is not available
     */
    private function migrationProblem(): ?array
    {
        if (!$this->migrations->isAvailable()) {
            return null;
        }

        try {
            $open = $this->migrations->count();
        } catch (Throwable $exception) {
            $this->logger?->warning('Dashboard system tile: the open migrations could not be read.', ['exception' => $exception]);

            return $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_migrations_error');
        }

        return $open > 0
            ? $this->problem(self::SEVERITY_WARNING, ['key' => 'sulu_dashboard.system_migrations_pending', 'parameters' => ['count' => $open]], 'sulu_dashboard.system_migrations_pending_hint')
            : null;
    }

    /**
     * @return array<string, mixed>|null null: no failed messages, or no failure transport
     */
    private function failedProblem(): ?array
    {
        if (!$this->failedMessages->isConfigured()) {
            return null;
        }

        try {
            $failed = $this->failedMessages->count();
        } catch (Throwable $exception) {
            $this->logger?->warning('Dashboard system tile: the failed messages could not be counted.', ['exception' => $exception]);

            return $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_failed_error');
        }

        if (null === $failed) {
            return $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_failed_error');
        }

        return $failed > 0
            ? $this->problem(self::SEVERITY_WARNING, ['key' => 'sulu_dashboard.system_failed_found', 'parameters' => ['count' => $failed]], 'sulu_dashboard.system_failed_found_hint')
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function lastCheckRow(): array
    {
        $parameters = ['command' => self::COMMAND];
        $age = $this->age();

        if (null === $age) {
            return $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_not_run', ['key' => 'sulu_dashboard.system_not_run_hint', 'parameters' => $parameters]);
        }

        if ($age > $this->maxAgeHours * 3600) {
            return $this->problem(self::SEVERITY_WARNING, $this->ageText($age), ['key' => 'sulu_dashboard.system_stale_hint', 'parameters' => $parameters]);
        }

        return $this->problem(self::SEVERITY_OK, $this->ageText($age), ['key' => 'sulu_dashboard.system_refresh_hint', 'parameters' => $parameters]);
    }

    private function isStale(SystemCheck $check): bool
    {
        return max(0, $this->time() - $check->checkedAt) > $this->maxAgeHours * 3600;
    }

    /**
     * Seconds since the last run of the console command; null if it never ran.
     */
    private function age(): ?int
    {
        $check = $this->store->load();

        return null === $check ? null : max(0, $this->time() - $check->checkedAt);
    }

    private function time(): int
    {
        return null === $this->now ? time() : (int) ($this->now)();
    }

    /**
     * @param string|array<string, mixed>      $title
     * @param string|array<string, mixed>|null $text
     *
     * @return array<string, mixed>
     */
    private function problem(string $severity, string|array $title, string|array|null $text = null): array
    {
        $row = ['title' => $title, 'severity' => $severity];
        if (null !== $text) {
            $row['text'] = $text;
        }

        return $row;
    }

    /**
     * @param string|array<string, mixed>      $title
     * @param string|array<string, mixed>      $value
     * @param string|array<string, mixed>|null $text
     *
     * @return array<string, mixed>
     */
    private function fact(string|array $title, string|array $value, ?string $severity = null, string|array|null $text = null): array
    {
        $row = ['title' => $title, 'meta' => $value];
        if (null !== $severity) {
            $row['severity'] = $severity;
        }
        if (null !== $text) {
            $row['text'] = $text;
        }

        return $row;
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
