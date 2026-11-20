<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * Turns what Composer wrote to its error output into a short text for the tile and the console, so that someone on the
 * server can see why a check did not run. Nothing leaves it that does not belong in the tile: colours and control
 * characters are gone, the project folder is shortened to "<project>", other paths and anything that looks like a secret
 * (password in a URL, token, key) are hidden, and the text is cut after 300 characters.
 */
final class ComposerFailure
{
    private const MAX_LENGTH = 300;

    private const NETWORK = [
        'curl error',
        'could not resolve host',
        'getaddrinfo',
        'failed to connect',
        'connection timed out',
        'connection refused',
        'network is unreachable',
        'could not connect',
        'failed to open stream',
        'ssl connect error',
        'ssl certificate problem',
        'timed out',
        'needs internet',
        'no internet',
    ];

    public static function isNetwork(string $text): bool
    {
        $text = strtolower($text);
        foreach (self::NETWORK as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function summarize(string $text, string $projectDir): ?string
    {
        $text = (string) preg_replace('/\x1B\[[0-9;?]*[ -\/]*[@-~]/', '', $text);
        $text = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text);

        foreach (array_unique([$projectDir, str_replace('\\', '/', $projectDir), str_replace('/', '\\', $projectDir)]) as $folder) {
            $folder = rtrim($folder, '\\/');
            if ('' !== $folder) {
                $text = str_ireplace($folder, '<project>', $text);
            }
        }

        $text = (string) preg_replace('#(://)[^/\s@]+@#', '$1***@', $text);
        $text = (string) preg_replace('/\b(gh[pousr]_|github_pat_|glpat-)[A-Za-z0-9_\-]+/', '***', $text);
        $text = (string) preg_replace('/((?:token|password|passwd|secret|authorization|api[_-]?key)[\w\-]*)(\s*[=:]\s*)(?:bearer\s+)?\S+/i', '$1$2***', $text);
        $text = (string) preg_replace('#(?<![A-Za-z0-9])[A-Za-z]:[\\\\/][^\s"\'<>:*?|]*#', '<path>', $text);
        $text = (string) preg_replace('#(?<![\w:/<>.\-])/(?:[\w.\-]+/)+[\w.\-]*#', '<path>', $text);
        $text = trim((string) preg_replace('/[\s\x{2500}-\x{257F}]+/u', ' ', $text));

        return '' === $text ? null : mb_strimwidth($text, 0, self::MAX_LENGTH, '...');
    }
}
