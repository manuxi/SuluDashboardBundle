<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use Closure;

/**
 * The environment variables for the Composer process. A web request often has none of what Composer needs: the
 * service account of Apache or PHP-FPM has no HOME (Linux) or APPDATA and LOCALAPPDATA (Windows), and Composer then stops
 * with "The APPDATA or COMPOSER_HOME environment variable must be set". Where neither COMPOSER_HOME nor a writable home
 * is there, Composer gets a folder of its own for its home and its cache. A COMPOSER_HOME the server already sets is
 * respected; a folder entered in sulu_dashboard.system.composer_home always wins. PATH and everything else is
 * inherited from PHP by the Symfony process.
 */
final class ComposerEnvironment
{
    /**
     * @param string       $defaultHome    folder used if the environment brings no usable home
     * @param string|null  $configuredHome sulu_dashboard.system.composer_home: if set, always used
     * @param Closure|null $getenv         fn (string): string|false, for tests (default getenv())
     * @param bool|null    $windows        for tests (default: the operating system)
     */
    public function __construct(
        private readonly string $defaultHome,
        private readonly ?string $configuredHome = null,
        private readonly ?Closure $getenv = null,
        private readonly ?bool $windows = null,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        $variables = ['COMPOSER_NO_INTERACTION' => '1'];
        $configured = null === $this->configuredHome ? '' : trim($this->configuredHome);
        $home = '' !== $configured ? $configured : ($this->needsOwnHome() ? $this->defaultHome : null);

        if (null !== $home) {
            if (!is_dir($home)) {
                @mkdir($home, 0775, true);
            }

            $variables['COMPOSER_HOME'] = $home;
            $variables['COMPOSER_CACHE_DIR'] = $home . '/cache';
        }

        // without SystemRoot Windows cannot resolve host names for the process
        if ($this->isWindows() && '' === $this->env('SystemRoot')) {
            $root = $this->env('windir');
            $root = '' !== $root ? $root : 'C:\Windows';
            if (is_dir($root)) {
                $variables['SystemRoot'] = $root;
            }
        }

        return $variables;
    }

    private function needsOwnHome(): bool
    {
        if ('' !== $this->env('COMPOSER_HOME')) {
            return false;
        }

        $folders = $this->isWindows() ? [$this->env('APPDATA'), $this->env('LOCALAPPDATA')] : [$this->env('HOME')];
        foreach ($folders as $folder) {
            if ('' === $folder || !is_dir($folder) || !is_writable($folder)) {
                return true;
            }
        }

        return false;
    }

    private function env(string $name): string
    {
        $value = null === $this->getenv ? getenv($name) : ($this->getenv)($name);

        return \is_string($value) ? $value : '';
    }

    private function isWindows(): bool
    {
        return $this->windows ?? 'Windows' === \PHP_OS_FAMILY;
    }
}
