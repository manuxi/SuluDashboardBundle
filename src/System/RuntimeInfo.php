<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * Cheap live values of the PHP runtime for the tile "Environment": memory limit, run time limit, OPcache, time zone and
 * default language. Read from the running process (web request or console), nothing is looked up on disk or online and
 * nothing secret is among them.
 */
final class RuntimeInfo
{
    public function __construct(
        public readonly string $memoryLimit,
        public readonly int $maxExecutionTime,
        public readonly bool $opcache,
        public readonly string $timezone,
        public readonly string $locale,
    ) {
    }

    public static function detect(string $defaultLocale = 'en'): self
    {
        $memory = \ini_get('memory_limit');
        $time = \ini_get('max_execution_time');
        // the console has its own switch for OPcache and it is mostly off there: the web value is what counts
        $enabled = 'cli' === \PHP_SAPI || 'phpdbg' === \PHP_SAPI ? \ini_get('opcache.enable_cli') : \ini_get('opcache.enable');

        return new self(
            \is_string($memory) ? $memory : '',
            \is_string($time) ? (int) $time : 0,
            \extension_loaded('Zend OPcache') && filter_var($enabled, \FILTER_VALIDATE_BOOLEAN),
            date_default_timezone_get(),
            $defaultLocale,
        );
    }

    /**
     * The limit in megabytes (php.ini notation "512M", "2G", "-1" for none); null for none or unreadable.
     */
    public function memoryLimitMegabytes(): ?int
    {
        $value = strtolower(trim($this->memoryLimit));
        if ('' === $value || '-1' === $value || 1 !== preg_match('/^(\d+)\s*([kmg]?)b?$/', $value, $match)) {
            return null;
        }

        $number = (int) $match[1];

        return match ($match[2]) {
            'g' => $number * 1024,
            'm' => $number,
            'k' => intdiv($number, 1024),
            default => intdiv($number, 1024 * 1024),
        };
    }
}
