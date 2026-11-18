<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Composer\InstalledVersions;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Versions of PHP, Symfony and Sulu, read from what is loaded and from Composer's runtime data (no shell, no network).
 */
final class SystemVersions
{
    public function __construct(
        public readonly string $php,
        public readonly string $symfony,
        public readonly ?string $sulu,
    ) {
    }

    public static function detect(): self
    {
        $sulu = null;
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('sulu/sulu')) {
            $sulu = InstalledVersions::getPrettyVersion('sulu/sulu');
        }

        return new self(\PHP_VERSION, Kernel::VERSION, null === $sulu ? null : ltrim($sulu, 'v'));
    }
}
