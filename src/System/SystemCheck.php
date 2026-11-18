<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * What the console command found out: the expensive checks, with the time they ran.
 */
final class SystemCheck
{
    /**
     * @param list<array{path: string, bytes: int}> $storage
     */
    public function __construct(
        public readonly int $checkedAt,
        public readonly AuditResult $audit,
        public readonly array $storage,
    ) {
    }

    /**
     * @return array{checkedAt: int, audit: array<string, mixed>, storage: list<array{path: string, bytes: int}>}
     */
    public function toArray(): array
    {
        return ['checkedAt' => $this->checkedAt, 'audit' => $this->audit->toArray(), 'storage' => $this->storage];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $audit = \is_array($data['audit'] ?? null) ? AuditResult::fromArray($data['audit']) : null;
        if (!\is_int($data['checkedAt'] ?? null) || null === $audit) {
            return null;
        }

        $storage = [];
        foreach (\is_array($data['storage'] ?? null) ? $data['storage'] : [] as $entry) {
            if (\is_array($entry) && \is_string($entry['path'] ?? null) && \is_int($entry['bytes'] ?? null)) {
                $storage[] = ['path' => $entry['path'], 'bytes' => $entry['bytes']];
            }
        }

        return new self($data['checkedAt'], $audit, $storage);
    }
}
