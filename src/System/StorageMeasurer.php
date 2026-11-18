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
 */
final class StorageMeasurer
{
    /**
     * @param list<string> $paths folders relative to the project directory
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly array $paths = ['public/uploads', 'var'],
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

            $result[] = ['path' => $path, 'bytes' => $this->size($directory)];
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
