<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\System;

/**
 * "1.4 GB" for en, "1,4 GB" for languages with a decimal comma.
 */
final class ByteFormatter
{
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    private const COMMA_LOCALES = ['de', 'nl', 'fr', 'es', 'it', 'pt'];

    public static function format(int $bytes, string $locale): string
    {
        $value = (float) max(0, $bytes);
        $unit = 0;
        while ($value >= 1024 && $unit < \count(self::UNITS) - 1) {
            $value /= 1024;
            ++$unit;
        }

        $comma = \in_array(strtolower(substr($locale, 0, 2)), self::COMMA_LOCALES, true);
        $number = number_format($value, 0 === $unit ? 0 : 1, $comma ? ',' : '.', '');

        return $number . ' ' . self::UNITS[$unit];
    }
}
