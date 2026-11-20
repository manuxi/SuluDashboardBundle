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

    /** Lines of the verbose output ("-vv", "-vvv") that say nothing about why Composer stopped. */
    private const NOISE = '/^(Reading |Loading |Checked CA|Executing command|Downloading |Running \d|> |\[\d{3}\] |Failed to initialize global composer|Writing |Importing |Found cached)/i';

    /**
     * Composer needs a GitHub token for a VCS repository of the project and has none: the API answers 403 (rate
     * limit without a token) and the fallback, a git clone over SSH, fails for a user without SSH keys.
     */
    public static function isGithubAuth(string $text): bool
    {
        $text = strtolower($text);

        return str_contains($text, '[403] https://api.github.com')
            || str_contains($text, 'could not authenticate against github.com')
            || (str_contains($text, 'failed to clone') && str_contains($text, 'github.com'));
    }

    /**
     * The reason in the error output of Composer, also if the output is verbose: the exception block ("In X.php line
     * 12:" with its message) as headline plus its last line (often the git or curl message), else the lines that are no
     * progress messages. Null if there is nothing.
     */
    public static function reason(string $text, string $projectDir): ?string
    {
        $text = (string) preg_replace('/\x1B\[[0-9;?]*[ -\/]*[@-~]/', '', $text);
        $lines = preg_split('/\R/', $text) ?: [];

        $block = null;
        foreach ($lines as $index => $line) {
            if (1 === preg_match('/^In .+ line \d+:\s*$/', $line)) {
                $block = \array_slice($lines, $index + 1);
                break;
            }
        }

        if (null !== $block) {
            $paragraphs = [[]];
            foreach ($block as $line) {
                if ('' !== trim($line) && 1 !== preg_match('/^\s/', $line)) {
                    break; // the usage line of the command ends the block
                }
                $line = trim($line);
                if ('' === $line) {
                    $paragraphs[] = [];
                } elseif (1 !== preg_match('/^\[[\w\\\\]+\]$/', $line)) {
                    $paragraphs[array_key_last($paragraphs)][] = $line;
                }
            }
            $paragraphs = array_values(array_filter($paragraphs));
            if ([] !== $paragraphs) {
                $head = implode(' ', $paragraphs[0]);
                if (\count($paragraphs) > 1) {
                    $last = $paragraphs[array_key_last($paragraphs)];
                    $head = mb_strimwidth($head, 0, 100, '...') . ' ... ' . implode(' ', \array_slice($last, -2));
                }

                return self::summarize($head, $projectDir);
            }
        }

        return self::summarize(implode("
", array_filter($lines, static fn (string $line): bool => 1 !== preg_match(self::NOISE, trim($line)))), $projectDir);
    }

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
