<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

/**
 * Places of the dashboard layout: a row of figures on top, below it a wide and a narrow column. MAIN and SIDE are a
 * preference: when one column ends up clearly longer, the dashboard moves tiles from its end into the shorter one
 * (sulu_dashboard.layout.balance). AUTO states no preference at all.
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
     * No preference: the tile goes into the column that is shorter at the moment (see sulu_dashboard.layout.balance).
     */
    public const AUTO = 'auto';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ROW, self::MAIN, self::SIDE, self::AUTO];
    }
}
