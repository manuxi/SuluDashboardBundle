<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Closure;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\System\AuditResult;
use Manuxi\SuluDashboardBundle\System\ByteFormatter;
use Manuxi\SuluDashboardBundle\System\FailedMessages;
use Manuxi\SuluDashboardBundle\System\PendingMigrations;
use Manuxi\SuluDashboardBundle\System\SystemCheck;
use Manuxi\SuluDashboardBundle\System\SystemCheckStore;
use Manuxi\SuluDashboardBundle\System\SystemVersions;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * "System": versions, environment, and what an administrator should look at (debug mode in production, known
 * vulnerabilities, open migrations, failed messages, disk use). Only for users with the permission
 * {@see DashboardAdmin::SYSTEM_SECURITY_CONTEXT}.
 *
 * Nothing slow runs here: the security check and the folder sizes are made by the console command
 * (sulu:dashboard:system-check, cron) and read from the cache pool. Nothing secret is shown: no connection strings, keys
 * or absolute paths, only versions, counts, package names and relative folder names.
 */
final class SystemWidget extends AbstractBuiltinWidget
{
    private const SEVERITY_ERROR = 'error';

    private const SEVERITY_WARNING = 'warning';

    private const MAX_PACKAGES = 10;

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
    ) {
    }

    public function getKey(): string
    {
        return 'sulu_system';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.system';
    }

    public function getType(): string
    {
        return WidgetTypes::LIST;
    }

    public function getSecurityContext(): string
    {
        return DashboardAdmin::SYSTEM_SECURITY_CONTEXT;
    }

    public function getColumn(): string
    {
        return WidgetColumns::SIDE;
    }

    public function getPriority(): int
    {
        return 5;
    }

    public function getData(string $locale): array
    {
        $check = $this->store->load();
        $now = null === $this->now ? time() : (int) ($this->now)();

        $errors = [];
        $warnings = [];
        $facts = [];
        $footer = null;

        $facts[] = $this->fact('PHP', $this->text($this->versions->php));
        $facts[] = $this->fact('Symfony', $this->text($this->versions->symfony));
        if (null !== $this->versions->sulu) {
            $facts[] = $this->fact('Sulu', $this->text($this->versions->sulu));
        }
        $facts[] = $this->fact('sulu_dashboard.system_environment', $this->text($this->environment));
        $facts[] = $this->fact('sulu_dashboard.system_debug', $this->debug ? 'sulu_dashboard.system_on' : 'sulu_dashboard.system_off');

        if ($this->debug && 'prod' === $this->environment) {
            $errors[] = $this->problem(self::SEVERITY_ERROR, 'sulu_dashboard.system_debug_in_prod', 'sulu_dashboard.system_debug_in_prod_hint');
        }

        // the expensive checks come from the console command
        if (null === $check) {
            $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_not_run', 'sulu_dashboard.system_not_run_hint');
        } else {
            [$auditProblems, $auditFacts, $footer] = $this->audit($check);
            array_push($errors, ...$auditProblems['errors']);
            array_push($warnings, ...$auditProblems['warnings']);
            array_push($facts, ...$auditFacts);

            foreach ($check->storage as $folder) {
                $facts[] = $this->fact($this->text($folder['path']), $this->text(ByteFormatter::format($folder['bytes'], $locale)));
            }

            $age = max(0, $now - $check->checkedAt);
            if ($age > $this->maxAgeHours * 3600) {
                $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_stale', $this->age($age));
            } else {
                $facts[] = $this->fact('sulu_dashboard.system_last_check', $this->age($age));
            }
        }

        if ($this->migrations->isAvailable()) {
            try {
                $open = $this->migrations->count();
                if ($open > 0) {
                    $warnings[] = $this->problem(self::SEVERITY_WARNING, ['key' => 'sulu_dashboard.system_migrations_pending', 'parameters' => ['count' => $open]], 'sulu_dashboard.system_migrations_pending_hint');
                } else {
                    $facts[] = $this->fact('sulu_dashboard.system_migrations', 'sulu_dashboard.system_migrations_ok');
                }
            } catch (Throwable $exception) {
                $this->logger?->warning('Dashboard system tile: the open migrations could not be read.', ['exception' => $exception]);
                $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_migrations_error');
            }
        }

        if ($this->failedMessages->isConfigured()) {
            try {
                $failed = $this->failedMessages->count();
                if (null === $failed) {
                    $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_failed_error');
                } elseif ($failed > 0) {
                    $warnings[] = $this->problem(self::SEVERITY_WARNING, ['key' => 'sulu_dashboard.system_failed_found', 'parameters' => ['count' => $failed]], 'sulu_dashboard.system_failed_found_hint');
                } else {
                    $facts[] = $this->fact('sulu_dashboard.system_failed_messages', 'sulu_dashboard.system_failed_none');
                }
            } catch (Throwable $exception) {
                $this->logger?->warning('Dashboard system tile: the failed messages could not be counted.', ['exception' => $exception]);
                $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_failed_error');
            }
        }

        return [
            'limit' => 30,
            'rows' => [...$errors, ...$warnings, ...$facts],
            'footer' => $footer,
        ];
    }

    /**
     * @return array{0: array{errors: list<array<string, mixed>>, warnings: list<array<string, mixed>>}, 1: list<array<string, mixed>>, 2: ?array<string, mixed>}
     */
    private function audit(SystemCheck $check): array
    {
        $errors = [];
        $warnings = [];
        $facts = [];
        $footer = null;
        $audit = $check->audit;

        if (AuditResult::OK === $audit->status) {
            $facts[] = $this->fact('sulu_dashboard.system_audit', 'sulu_dashboard.system_audit_ok');
        } elseif (AuditResult::VULNERABLE === $audit->status) {
            $errors[] = $this->problem(
                self::SEVERITY_ERROR,
                ['key' => 'sulu_dashboard.system_audit_found', 'parameters' => ['count' => \count($audit->advisories)]],
                'sulu_dashboard.system_audit_found_hint',
            );

            $rows = [];
            foreach (\array_slice($audit->advisories, 0, self::MAX_PACKAGES) as $advisory) {
                $label = $advisory['package'] . ': ' . $advisory['title'] . (null === $advisory['cve'] ? '' : ' (' . $advisory['cve'] . ')');
                $rows[] = ['title' => $this->text($label)];
            }
            if (\count($audit->advisories) > self::MAX_PACKAGES) {
                $rows[] = ['title' => ['key' => 'sulu_dashboard.system_audit_more', 'parameters' => ['count' => \count($audit->advisories) - self::MAX_PACKAGES]]];
            }
            $footer = ['title' => 'sulu_dashboard.system_audit_packages', 'rows' => $rows];
        } else {
            $known = [AuditResult::ERROR_NOT_FOUND, AuditResult::ERROR_TIMEOUT];
            $reason = \in_array($audit->error, $known, true) ? $audit->error : AuditResult::ERROR_FAILED;
            $warnings[] = $this->problem(self::SEVERITY_WARNING, 'sulu_dashboard.system_audit_unavailable', 'sulu_dashboard.system_audit_error_' . $reason);
        }

        return [['errors' => $errors, 'warnings' => $warnings], $facts, $footer];
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
     * @param string|array<string, mixed> $title
     * @param string|array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function fact(string|array $title, string|array $value): array
    {
        return ['title' => $title, 'meta' => $value];
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
    private function age(int $seconds): array
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
