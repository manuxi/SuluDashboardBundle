<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * Result of "composer audit": no vulnerabilities, a list of them, or the reason why the check could not run. Only
 * public data of an advisory is kept (package, installed version, advisory id, CVE, title, link, severity, date), never
 * paths or raw output. Abandoned packages are reported next to them.
 *
 * @phpstan-type Advisory array{package: string, installed: ?string, id: ?string, cve: ?string, title: string, link: ?string, severity: ?string, reportedAt: ?string}
 * @phpstan-type Abandoned array{package: string, replacement: ?string}
 */
final class AuditResult
{
    public const OK = 'ok';

    public const VULNERABLE = 'vulnerable';

    public const UNAVAILABLE = 'unavailable';

    public const ERROR_NOT_FOUND = 'not_found';

    public const ERROR_FAILED = 'failed';

    public const ERROR_TIMEOUT = 'timeout';

    public const ERROR_NETWORK = 'network';

    /**
     * @param list<Advisory>  $advisories
     * @param list<Abandoned> $abandoned
     */
    private function __construct(
        public readonly string $status,
        public readonly array $advisories,
        public readonly ?string $error,
        public readonly array $abandoned = [],
        public readonly ?string $detail = null,
    ) {
    }

    /**
     * @param list<Abandoned> $abandoned
     */
    public static function ok(array $abandoned = []): self
    {
        return new self(self::OK, [], null, $abandoned);
    }

    /**
     * @param list<Advisory>  $advisories
     * @param list<Abandoned> $abandoned
     */
    public static function vulnerable(array $advisories, array $abandoned = []): self
    {
        return [] === $advisories ? self::ok($abandoned) : new self(self::VULNERABLE, $advisories, null, $abandoned);
    }

    /**
     * @param string|null $detail why, in Composer's words, shortened and without paths (see {@see ComposerFailure})
     */
    public static function unavailable(string $error, ?string $detail = null): self
    {
        return new self(self::UNAVAILABLE, [], $error, [], $detail);
    }

    /**
     * @return array{status: string, advisories: list<Advisory>, abandoned: list<Abandoned>, error: ?string, detail: ?string}
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'advisories' => $this->advisories, 'abandoned' => $this->abandoned, 'error' => $this->error, 'detail' => $this->detail];
    }

    /**
     * Reads what {@see toArray()} wrote. An older cache entry (fewer fields) is still understood: the missing fields
     * are empty until the command runs again.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $status = $data['status'] ?? null;
        $abandoned = self::abandonedFrom($data['abandoned'] ?? null);

        if (self::OK === $status) {
            return self::ok($abandoned);
        }

        if (self::UNAVAILABLE === $status) {
            return self::unavailable(\is_string($data['error'] ?? null) ? $data['error'] : self::ERROR_FAILED, self::string($data, 'detail'));
        }

        if (self::VULNERABLE !== $status || !\is_array($data['advisories'] ?? null)) {
            return null;
        }

        $advisories = [];
        foreach ($data['advisories'] as $advisory) {
            if (\is_array($advisory) && \is_string($advisory['package'] ?? null) && \is_string($advisory['title'] ?? null)) {
                $advisories[] = [
                    'package' => $advisory['package'],
                    'installed' => self::string($advisory, 'installed'),
                    'id' => self::string($advisory, 'id'),
                    'cve' => self::string($advisory, 'cve'),
                    'title' => $advisory['title'],
                    'link' => self::string($advisory, 'link'),
                    'severity' => self::string($advisory, 'severity'),
                    'reportedAt' => self::string($advisory, 'reportedAt'),
                ];
            }
        }

        return self::vulnerable($advisories, $abandoned);
    }

    /**
     * @return list<Abandoned>
     */
    private static function abandonedFrom(mixed $data): array
    {
        $result = [];
        foreach (\is_array($data) ? $data : [] as $entry) {
            if (\is_array($entry) && \is_string($entry['package'] ?? null)) {
                $result[] = ['package' => $entry['package'], 'replacement' => self::string($entry, 'replacement')];
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): ?string
    {
        return \is_string($data[$key] ?? null) && '' !== $data[$key] ? $data[$key] : null;
    }
}
