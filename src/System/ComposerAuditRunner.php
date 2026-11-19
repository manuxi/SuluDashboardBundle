<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Closure;
use Composer\InstalledVersions;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs "composer audit --locked" in the project folder. Only used by the console command: starting a process has no
 * place in a web request. The command is passed as a list (no shell), the output is read as JSON and reduced to
 * the fields the "System" tab shows (package, installed version, advisory id, CVE, title, link, severity, date) and the
 * abandoned packages.
 *
 * @phpstan-import-type Advisory from AuditResult
 * @phpstan-import-type Abandoned from AuditResult
 */
final class ComposerAuditRunner implements AuditRunnerInterface
{
    private const MAX_ADVISORIES = 200;

    private const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    public function __construct(
        private readonly string $projectDir,
        private readonly string $composer = 'composer',
        private readonly int $timeout = 120,
        private readonly ?Closure $installedVersion = null,
    ) {
    }

    public function run(): AuditResult
    {
        $command = ComposerCommand::resolve($this->composer);
        if (null === $command) {
            return AuditResult::unavailable(AuditResult::ERROR_NOT_FOUND);
        }

        $process = new Process([...$command, 'audit', '--locked', '--format=json', '--no-interaction'], $this->projectDir, ['COMPOSER_NO_INTERACTION' => '1'], null, $this->timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return AuditResult::unavailable(AuditResult::ERROR_TIMEOUT);
        } catch (Throwable) {
            return AuditResult::unavailable(AuditResult::ERROR_FAILED);
        }

        // exit code 1 means "vulnerabilities found" and still comes with the JSON
        $data = json_decode($process->getOutput(), true);
        if (!\is_array($data)) {
            return AuditResult::unavailable(AuditResult::ERROR_FAILED);
        }

        return AuditResult::vulnerable($this->advisories($data), $this->abandoned($data));
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<Advisory>
     */
    private function advisories(array $data): array
    {
        $result = [];
        $packages = \is_array($data['advisories'] ?? null) ? $data['advisories'] : [];

        foreach ($packages as $name => $list) {
            foreach (\is_array($list) ? $list : [] as $advisory) {
                if (!\is_array($advisory)) {
                    continue;
                }

                $package = $this->clean(\is_string($advisory['packageName'] ?? null) ? $advisory['packageName'] : (string) $name, 100);
                $severity = \is_string($advisory['severity'] ?? null) ? strtolower(trim($advisory['severity'])) : null;

                $result[] = [
                    'package' => $package,
                    'installed' => $this->installed($package),
                    'id' => $this->optional($advisory['advisoryId'] ?? null, 60),
                    'cve' => $this->optional($advisory['cve'] ?? null, 30),
                    'title' => $this->clean(\is_string($advisory['title'] ?? null) ? $advisory['title'] : '', 160),
                    'link' => $this->link($advisory['link'] ?? null),
                    'severity' => \in_array($severity, self::SEVERITIES, true) ? $severity : null,
                    'reportedAt' => $this->optional($advisory['reportedAt'] ?? null, 40),
                ];

                if (\count($result) >= self::MAX_ADVISORIES) {
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * "abandoned" is a map package => replacement package (or null/false without a replacement).
     *
     * @param array<mixed> $data
     *
     * @return list<Abandoned>
     */
    private function abandoned(array $data): array
    {
        $result = [];
        foreach (\is_array($data['abandoned'] ?? null) ? $data['abandoned'] : [] as $package => $replacement) {
            if (!\is_string($package) || '' === $package) {
                continue;
            }

            $result[] = ['package' => $this->clean($package, 100), 'replacement' => $this->optional($replacement, 100)];
        }

        return $result;
    }

    /**
     * Version of the package as installed in this project; null if it cannot be read.
     */
    private function installed(string $package): ?string
    {
        try {
            if (null !== $this->installedVersion) {
                $version = ($this->installedVersion)($package);
            } else {
                $version = class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package) ? InstalledVersions::getPrettyVersion($package) : null;
            }
        } catch (Throwable) {
            return null;
        }

        return \is_string($version) && '' !== $version ? $this->clean($version, 40) : null;
    }

    /**
     * Only a web link is kept: the title in the tab is a link, and "javascript:" or "data:" must never get that far.
     */
    private function link(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return 1 === preg_match('#^https?://[^\s<>"\']+$#i', $value) && \strlen($value) <= 500 ? $value : null;
    }

    private function optional(mixed $value, int $length): ?string
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return $this->clean($value, $length);
    }

    private function clean(string $text, int $length): string
    {
        return mb_strimwidth(trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text)), 0, $length, '...');
    }
}
