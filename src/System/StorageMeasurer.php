<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Adds up the size of folders of the project. Slow on big folders, so it only runs in the console command, never in a
 * web request. Symbolic links inside a folder are not followed, folders that do not exist are left out. Only the
 * relative path is reported, never the absolute one.
 *
 * A folder that is listed in $splitPaths ("var") is reported by its subfolders ("var/cache", "var/log", ...), so the tile
 * can show what takes the room. Files that lie directly in it are reported under the path of the folder itself.
 */
final class StorageMeasurer
{
    /**
     * @param list<string> $paths      folders relative to the project directory
     * @param list<string> $splitPaths folders of $paths that are reported by their subfolders
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly array $paths = ['public/uploads', 'var'],
        private readonly array $splitPaths = ['var'],
    ) {
    }

    /**
     * @return list<array{path: string, bytes: int}>
     */
    public function measure(): array
    {
        $result = [];
        foreach ($this->paths as $path) {
            $directory = $this->projectDir . '/' . $path;
            if (!is_dir($directory)) {
                continue;
            }

            if (\in_array($path, $this->splitPaths, true)) {
                array_push($result, ...$this->measureSplit($path, $directory));

                continue;
            }

            $result[] = ['path' => $path, 'bytes' => $this->size($directory)];
        }

        return $result;
    }

    /**
     * @return list<array{path: string, bytes: int}>
     */
    private function measureSplit(string $path, string $directory): array
    {
        $result = [];
        $loose = 0;

        $entries = @scandir($directory) ?: [];
        sort($entries);
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $child = $directory . '/' . $entry;
            if (is_link($child)) {
                continue;
            }

            if (is_dir($child)) {
                $result[] = ['path' => $path . '/' . $entry, 'bytes' => $this->size($child)];
            } elseif (is_file($child)) {
                $loose += (int) @filesize($child);
            }
        }

        if ($loose > 0) {
            $result[] = ['path' => $path, 'bytes' => $loose];
        }

        return $result;
    }

    private function size(string $directory): int
    {
        $size = 0;

        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );

            foreach ($files as $file) {
                try {
                    if ($file->isFile() && !$file->isLink()) {
                        $size += (int) $file->getSize();
                    }
                } catch (Throwable) {
                    // a file that vanished while counting
                }
            }
        } catch (Throwable) {
            // an unreadable folder counts what could be read
        }

        return $size;
    }
}
