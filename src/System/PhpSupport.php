<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

use DateTimeImmutable;

/**
 * Until when a PHP version gets security fixes. A fixed table, taken from https://www.php.net/supported-versions.php
 * (column "Security Support Until"); a new minor version is added when it is released. Nothing is asked online.
 */
final class PhpSupport
{
    public const STATE_OK = 'ok';

    /** support ends within the warning period */
    public const STATE_ENDING = 'ending';

    public const STATE_ENDED = 'ended';

    /** a version the table does not know (newer than the table) */
    public const STATE_UNKNOWN = 'unknown';

    /**
     * Minor version => last day of security support.
     *
     * @var array<string, string>
     */
    public const SECURITY_UNTIL = [
        '7.4' => '2022-11-28',
        '8.0' => '2023-11-26',
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
        '8.5' => '2029-12-31',
    ];

    /**
     * @return array{state: string, until: ?string} until: "Y-m-d" of the last day of security support, null if unknown
     *                                              (versions before 7.4 count as ended without a date)
     */
    public static function check(string $version, DateTimeImmutable $now, int $warnDays = 180): array
    {
        if (1 !== preg_match('/^(\d+)\.(\d+)/', $version, $match)) {
            return ['state' => self::STATE_UNKNOWN, 'until' => null];
        }

        $minor = $match[1] . '.' . $match[2];
        if (!isset(self::SECURITY_UNTIL[$minor])) {
            return version_compare($minor, '7.4', '<')
                ? ['state' => self::STATE_ENDED, 'until' => null]
                : ['state' => self::STATE_UNKNOWN, 'until' => null];
        }

        $until = self::SECURITY_UNTIL[$minor];
        $end = new DateTimeImmutable($until . ' 23:59:59');
        if ($end < $now) {
            $state = self::STATE_ENDED;
        } elseif ($end < $now->modify('+' . $warnDays . ' days')) {
            $state = self::STATE_ENDING;
        } else {
            $state = self::STATE_OK;
        }

        return ['state' => $state, 'until' => $until];
    }
}
