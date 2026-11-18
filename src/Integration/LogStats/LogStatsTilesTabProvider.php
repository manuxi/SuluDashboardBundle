<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Integration\LogStats;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Tab\RemovesReplacedViewsInterface;
use Manuxi\SuluDashboardBundle\Tab\TilesTabProviderInterface;

/**
 * The tab "Visitors" built from tiles (sulu_dashboard.log_stats.tab: tiles, the default): the figures, charts, top lists
 * and the table of days of the log statistics are tiles of the group "visitors" that the user can arrange, hide and
 * reset like the tiles of the overview. The page of the other bundle is replaced: its menu item and its view go, and its
 * path opens this tab.
 */
final class LogStatsTilesTabProvider extends LogStatsTabProvider implements TilesTabProviderInterface, RemovesReplacedViewsInterface
{
    public const GROUP = 'visitors';

    /** days to choose in the toolbar, next to "Custom period"; the API answers at most 366 days */
    public const PERIODS = [7, 30, 90, 365];

    public const DEFAULT_PERIOD = 30;

    public function getViewType(): string
    {
        return DashboardAdmin::VIEW;
    }

    public function getViewOptions(): array
    {
        return [];
    }

    public function getGroup(): string
    {
        return self::GROUP;
    }

    public function getPeriods(): array
    {
        return self::PERIODS;
    }

    public function getDefaultPeriod(): int
    {
        return self::DEFAULT_PERIOD;
    }

    public function showLocale(): bool
    {
        return false;
    }
}
