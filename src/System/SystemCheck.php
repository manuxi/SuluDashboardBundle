<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * What the console command found out: the expensive checks, with the time they ran. The measurement before it
 * ("previous") is kept so that the tile can show what changed since then.
 *
 * @phpstan-type Storage list<array{path: string, bytes: int}>
 * @phpstan-type Media array{count: int, bytes: int}
 * @phpstan-type Previous array{checkedAt: int, storage: Storage, media: Media|null}
 */
final class SystemCheck
{
    /**
     * @param Storage       $storage
     * @param Media|null    $media    number and size of the media of Sulu; null if they could not be read
     * @param Previous|null $previous the measurement before this one; null at the first run
     */
    public function __construct(
        public readonly int $checkedAt,
        public readonly AuditResult $audit,
        public readonly array $storage,
        public readonly ?array $media = null,
        public readonly ?array $previous = null,
    ) {
    }

    /**
     * @return array{checkedAt: int, audit: array<string, mixed>, storage: Storage, media: Media|null, previous: Previous|null}
     */
    public function toArray(): array
    {
        return [
            'checkedAt' => $this->checkedAt,
            'audit' => $this->audit->toArray(),
            'storage' => $this->storage,
            'media' => $this->media,
            'previous' => $this->previous,
        ];
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

        $previous = null;
        if (\is_array($data['previous'] ?? null) && \is_int($data['previous']['checkedAt'] ?? null)) {
            $previous = [
                'checkedAt' => $data['previous']['checkedAt'],
                'storage' => self::storage($data['previous']['storage'] ?? null),
                'media' => self::media($data['previous']['media'] ?? null),
            ];
        }

        return new self($data['checkedAt'], $audit, self::storage($data['storage'] ?? null), self::media($data['media'] ?? null), $previous);
    }

    /**
     * @return Storage
     */
    private static function storage(mixed $value): array
    {
        $storage = [];
        foreach (\is_array($value) ? $value : [] as $entry) {
            if (\is_array($entry) && \is_string($entry['path'] ?? null) && \is_int($entry['bytes'] ?? null)) {
                $storage[] = ['path' => $entry['path'], 'bytes' => $entry['bytes']];
            }
        }

        return $storage;
    }

    /**
     * @return Media|null
     */
    private static function media(mixed $value): ?array
    {
        if (\is_array($value) && \is_int($value['count'] ?? null) && \is_int($value['bytes'] ?? null)) {
            return ['count' => $value['count'], 'bytes' => $value['bytes']];
        }

        return null;
    }
}
