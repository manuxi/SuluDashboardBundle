<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

/**
 * Places of the dashboard layout: a row of figures on top, below it a wide and a narrow column.
 */
final class WidgetColumns
{
    /** Row of figures on top, all tiles of equal height. Meant for the type "number". */
    public const ROW = 'row';

    /** Wide column (two thirds). */
    public const MAIN = 'main';

    /** Narrow column (one third). */
    public const SIDE = 'side';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ROW, self::MAIN, self::SIDE];
    }
}
