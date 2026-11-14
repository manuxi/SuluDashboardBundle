<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

/**
 * Kinds of display of a tile.
 */
final class WidgetTypes
{
    /** A figure with a label and an optional split by state. */
    public const NUMBER = 'number';

    /** One or more values against a maximum, as rings or bars. */
    public const PROGRESS = 'progress';

    /** A short table of entries with an optional link each. */
    public const LIST = 'list';

    /** Bars that compare values with each other. */
    public const BARS = 'bars';

    /** A React component of its own, registered in the JavaScript widgetRegistry under the key of the tile. */
    public const CUSTOM = 'custom';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::NUMBER, self::PROGRESS, self::LIST, self::BARS, self::CUSTOM];
    }
}
