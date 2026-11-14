<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Integration\LogStats;

use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

/**
 * Tile "Visitors (7 days)". The figures come from the admin API of the log statistics, which the React component
 * "sulu_visitors" reads itself (like the activity tile reads the activity API of Sulu): this class only hands over where
 * to read. Same permission as the page of that bundle.
 */
final class VisitorsWidget implements DashboardWidgetInterface
{
    public function __construct(private readonly LogStatsTabProvider $provider)
    {
    }

    public function getKey(): string
    {
        return 'sulu_visitors';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.visitors_week';
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
        return WidgetColumns::SIDE;
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function getData(string $locale): array
    {
        return ['apiUrl' => $this->provider->apiUrl(), 'days' => 7];
    }
}
