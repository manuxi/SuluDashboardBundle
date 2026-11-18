<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Integration\LogStats;

use InvalidArgumentException;
use Manuxi\SuluDashboardBundle\Widget\GroupedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\PinnedDashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * One tile of the tab "Visitors": a figure, a chart, a top list or the table of days, all read from the admin API of the
 * log statistics. One class, registered once per entry of {@see TILES}; the React component (widgets/VisitorTile.js)
 * learns from the data which metric to show and where to read. The server hands over only that: the numbers come from the
 * browser, which reads the answer once for all tiles and for the period of the tab. Same permission as the page of that
 * bundle.
 */
final class LogStatsTileWidget implements GroupedDashboardWidgetInterface, PinnedDashboardWidgetInterface
{
    public const KEY_PREFIX = 'sulu_visitors_';

    /**
     * Metric => [title, column, priority]. The metrics are the ones of src/Resources/js/logStats.js (METRICS). The
     * figures form the row, the charts and the table the wide column, the top lists the narrow one.
     *
     * @var array<string, array{string, string, int}>
     */
    public const TILES = [
        'views' => ['sulu_dashboard.page_views', WidgetColumns::ROW, 90],
        'visitors' => ['sulu_dashboard.visitors_count', WidgetColumns::ROW, 89],
        'requests' => ['sulu_dashboard.visitors_requests', WidgetColumns::ROW, 88],
        'bots' => ['sulu_dashboard.visitors_bot_share', WidgetColumns::ROW, 87],
        'client_errors' => ['sulu_dashboard.visitors_client_errors', WidgetColumns::ROW, 86],
        'server_errors' => ['sulu_dashboard.visitors_server_errors', WidgetColumns::ROW, 85],
        'response_time' => ['sulu_dashboard.visitors_response_time', WidgetColumns::ROW, 84],
        'bytes' => ['sulu_dashboard.visitors_bytes', WidgetColumns::ROW, 83],
        'chart_views' => ['sulu_dashboard.visitors_chart_views', WidgetColumns::MAIN, 70],
        'chart_visitors' => ['sulu_dashboard.visitors_chart_visitors', WidgetColumns::MAIN, 69],
        'chart_status' => ['sulu_dashboard.visitors_chart_status', WidgetColumns::MAIN, 68],
        'chart_hours' => ['sulu_dashboard.visitors_chart_hours', WidgetColumns::MAIN, 67],
        'chart_response' => ['sulu_dashboard.visitors_chart_response', WidgetColumns::MAIN, 66],
        'daily' => ['sulu_dashboard.visitors_daily', WidgetColumns::MAIN, 60],
        'top_pages' => ['sulu_dashboard.visitors_top_pages', WidgetColumns::SIDE, 50],
        'top_not_found' => ['sulu_dashboard.visitors_top_not_found', WidgetColumns::SIDE, 49],
        'top_errors' => ['sulu_dashboard.visitors_top_errors', WidgetColumns::SIDE, 48],
    ];

    public function __construct(
        private readonly LogStatsTabProvider $provider,
        private readonly string $metric,
    ) {
        if (!isset(self::TILES[$metric])) {
            throw new InvalidArgumentException(\sprintf('Unknown visitors metric "%s". Known: %s.', $metric, implode(', ', array_keys(self::TILES))));
        }
    }

    public function getKey(): string
    {
        return self::KEY_PREFIX . $this->metric;
    }

    public function getTitle(): string
    {
        return self::TILES[$this->metric][0];
    }

    public function getType(): string
    {
        return WidgetTypes::CUSTOM;
    }

    public function getSecurityContext(): string
    {
        return LogStatsTabProvider::SECURITY_CONTEXT;
    }

    public function getColumn(): string
    {
        return self::TILES[$this->metric][1];
    }

    public function getPriority(): int
    {
        return self::TILES[$this->metric][2];
    }

    /**
     * The table of days has eight columns and needs the wide column: the balance never moves it into the narrow one.
     */
    public function isPinned(): bool
    {
        return 'daily' === $this->metric;
    }

    public function getGroup(): string
    {
        return LogStatsTilesTabProvider::GROUP;
    }

    public function getData(string $locale): array
    {
        return ['metric' => $this->metric, 'apiUrl' => $this->provider->apiUrl(), 'timezone' => $this->provider->getTimezone()];
    }
}
