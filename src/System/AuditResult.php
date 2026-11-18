<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * Result of "composer audit": no vulnerabilities, a list of them, or the reason why the check could not run. Only
 * package names and the public title and CVE of an advisory are kept, never paths or raw output.
 */
final class AuditResult
{
    public const OK = 'ok';

    public const VULNERABLE = 'vulnerable';

    public const UNAVAILABLE = 'unavailable';

    public const ERROR_NOT_FOUND = 'not_found';

    public const ERROR_FAILED = 'failed';

    public const ERROR_TIMEOUT = 'timeout';

    /**
     * @param list<array{package: string, title: string, cve: ?string}> $advisories
     */
    private function __construct(
        public readonly string $status,
        public readonly array $advisories,
        public readonly ?string $error,
    ) {
    }

    public static function ok(): self
    {
        return new self(self::OK, [], null);
    }

    /**
     * @param list<array{package: string, title: string, cve: ?string}> $advisories
     */
    public static function vulnerable(array $advisories): self
    {
        return [] === $advisories ? self::ok() : new self(self::VULNERABLE, $advisories, null);
    }

    public static function unavailable(string $error): self
    {
        return new self(self::UNAVAILABLE, [], $error);
    }

    /**
     * @return array{status: string, advisories: list<array{package: string, title: string, cve: ?string}>, error: ?string}
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'advisories' => $this->advisories, 'error' => $this->error];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $status = $data['status'] ?? null;
        if (self::OK === $status) {
            return self::ok();
        }

        if (self::UNAVAILABLE === $status) {
            return self::unavailable(\is_string($data['error'] ?? null) ? $data['error'] : self::ERROR_FAILED);
        }

        if (self::VULNERABLE !== $status || !\is_array($data['advisories'] ?? null)) {
            return null;
        }

        $advisories = [];
        foreach ($data['advisories'] as $advisory) {
            if (\is_array($advisory) && \is_string($advisory['package'] ?? null) && \is_string($advisory['title'] ?? null)) {
                $advisories[] = [
                    'package' => $advisory['package'],
                    'title' => $advisory['title'],
                    'cve' => \is_string($advisory['cve'] ?? null) ? $advisory['cve'] : null,
                ];
            }
        }

        return self::vulnerable($advisories);
    }
}
