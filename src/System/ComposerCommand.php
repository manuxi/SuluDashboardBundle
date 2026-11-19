<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Finds the command that starts Composer, as a list for a process (no shell): the binary, or PHP and a composer.phar.
 */
final class ComposerCommand
{
    /**
     * @return list<string>|null null if Composer was not found
     */
    public static function resolve(string $composer): ?array
    {
        if (str_ends_with($composer, '.phar')) {
            $php = (new PhpExecutableFinder())->find(false);

            return false !== $php && is_file($composer) ? [$php, $composer] : null;
        }

        $binary = is_file($composer) ? $composer : (new ExecutableFinder())->find($composer);

        return null === $binary ? null : [$binary];
    }
}
