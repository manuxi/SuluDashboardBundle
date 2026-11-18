<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\System;

use Manuxi\SuluDashboardBundle\System\ByteFormatter;
use Manuxi\SuluDashboardBundle\System\SystemCheck;

/**
 * The data of the tile "Storage" (component widgets/SystemStorage.js): what the folders hold, each as a share of the sum,
 * what changed since the measurement before, the media of Sulu, and a short hint where a folder is bigger than it should be.
 *
 * Reads only the result of the console command. The sum is the sum of the measured folders (public/uploads and the folders
 * of var), not the size of the disk: on shared hosting the free space of the disk is the space of the whole server and not
 * the quota of the account, so it is left out. Texts are admin translation keys, {key, parameters} or {text}.
 */
final class StorageReport
{
    /** bars shown at most; what is left is summed up in one bar */
    public const MAX_ITEMS = 6;

    /** a change below this is not shown (1 MB) */
    public const MIN_CHANGE = 1048576;

    public const CACHE_PATH = 'var/cache';

    public const LOG_PATH = 'var/log';

    /** @var array<string, string> */
    private const LABELS = [
        'public/uploads' => 'sulu_dashboard.system_storage_uploads',
        self::CACHE_PATH => 'sulu_dashboard.system_storage_cache',
        self::LOG_PATH => 'sulu_dashboard.system_storage_logs',
        'var/storage' => 'sulu_dashboard.system_storage_files',
        'var' => 'sulu_dashboard.system_storage_other',
    ];

    /**
     * @param int $cacheLimitMb a hint when var/cache is bigger than this, in MB (0: no hint)
     * @param int $logLimitMb   a hint when var/log is bigger than this, in MB (0: no hint)
     */
    public function __construct(
        private readonly int $cacheLimitMb = 1024,
        private readonly int $logLimitMb = 512,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(SystemCheck $check, string $locale, int $now, bool $stale): array
    {
        $folders = array_values(array_filter($check->storage, static fn (array $folder): bool => $folder['bytes'] > 0));
        usort($folders, static fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);
        $total = array_sum(array_column($folders, 'bytes'));

        $before = [];
        foreach (null === $check->previous ? [] : $check->previous['storage'] as $folder) {
            $before[$folder['path']] = $folder['bytes'];
        }
        $hasBefore = null !== $check->previous;

        $items = [];
        $rest = null;
        foreach ($folders as $index => $folder) {
            $change = $hasBefore ? $folder['bytes'] - ($before[$folder['path']] ?? 0) : null;
            if ($index >= self::MAX_ITEMS - 1 && \count($folders) > self::MAX_ITEMS) {
                $rest ??= ['bytes' => 0, 'count' => 0, 'change' => $hasBefore ? 0 : null];
                $rest['bytes'] += $folder['bytes'];
                ++$rest['count'];
                $rest['change'] = null === $change ? null : $rest['change'] + $change;

                continue;
            }

            $items[] = $this->item(
                self::LABELS[$folder['path']] ?? ['text' => $folder['path']],
                'var' === $folder['path'] ? null : $folder['path'],
                $folder['bytes'],
                $total,
                $change,
                $locale,
                $this->isTooBig($folder['path'], $folder['bytes']),
            );
        }

        if (null !== $rest) {
            $items[] = $this->item(['key' => 'sulu_dashboard.system_storage_rest', 'parameters' => ['count' => $rest['count']]], null, $rest['bytes'], $total, $rest['change'], $locale, false);
        }

        $previousTotal = $hasBefore ? array_sum(array_column($check->previous['storage'] ?? [], 'bytes')) : null;
        $age = SystemTileData::ageText(max(0, $now - $check->checkedAt));

        return [
            'status' => 'ok',
            'command' => SystemTileData::COMMAND,
            'total' => ['text' => ByteFormatter::format($total, $locale), 'change' => null === $previousTotal ? null : $this->change($total - $previousTotal, $locale)],
            'age' => $age,
            'stale' => $stale,
            'previousAge' => null === $check->previous ? null : SystemTileData::ageText(max(0, $now - $check->previous['checkedAt'])),
            'items' => $items,
            'media' => null === $check->media ? null : ['count' => $check->media['count'], 'text' => ByteFormatter::format($check->media['bytes'], $locale)],
            'hints' => $this->hints($check, $locale),
        ];
    }

    /**
     * What is bigger than it should be: one entry per folder, with the size as a parameter.
     *
     * @return list<array{severity: string, title: array{key: string, parameters: array{size: string}}, text: string}>
     */
    public function hints(SystemCheck $check, string $locale = 'en'): array
    {
        $hints = [];
        foreach ($check->storage as $folder) {
            $key = match (true) {
                self::CACHE_PATH === $folder['path'] && $this->isTooBig($folder['path'], $folder['bytes']) => 'cache',
                self::LOG_PATH === $folder['path'] && $this->isTooBig($folder['path'], $folder['bytes']) => 'logs',
                default => null,
            };

            if (null !== $key) {
                $hints[] = [
                    'severity' => SystemTileData::SEVERITY_WARNING,
                    'title' => ['key' => 'sulu_dashboard.system_storage_' . $key . '_large', 'parameters' => ['size' => ByteFormatter::format($folder['bytes'], $locale)]],
                    'text' => 'sulu_dashboard.system_storage_' . $key . '_large_hint',
                ];
            }
        }

        return $hints;
    }

    private function isTooBig(string $path, int $bytes): bool
    {
        $limit = match ($path) {
            self::CACHE_PATH => $this->cacheLimitMb,
            self::LOG_PATH => $this->logLimitMb,
            default => 0,
        };

        return $limit > 0 && $bytes > $limit * 1048576;
    }

    /**
     * @param string|array<string, mixed> $label
     *
     * @return array<string, mixed>
     */
    private function item(string|array $label, ?string $path, int $bytes, int $total, ?int $change, string $locale, bool $warning): array
    {
        return [
            'label' => $label,
            'path' => $path,
            'bytes' => $bytes,
            'max' => max(1, $total),
            'text' => ByteFormatter::format($bytes, $locale),
            'percent' => $total > 0 ? round($bytes / $total * 100, 1) : 0,
            'skin' => $warning ? 'warning' : 'progress',
            'change' => null === $change ? null : $this->change($change, $locale),
        ];
    }

    /**
     * "+12,3 MB" or "−4,1 MB"; null for a change that is too small to mention.
     *
     * @return array{text: string, direction: string}|null
     */
    private function change(int $delta, string $locale): ?array
    {
        if (abs($delta) < self::MIN_CHANGE) {
            return null;
        }

        return ['text' => ($delta > 0 ? '+' : '−') . ByteFormatter::format(abs($delta), $locale), 'direction' => $delta > 0 ? 'up' : 'down'];
    }
}
