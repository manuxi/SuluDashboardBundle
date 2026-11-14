<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Integration\LogStats;

use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The statistics page of amoifr/sulu-log-stats-bundle as the tab "Visitors". Loaded only when that bundle is installed
 * and enabled (see SuluDashboardExtension). The view type and the options are the ones its own admin class sets for its
 * menu item, so the page is the same one; nothing of that bundle is referenced by class.
 */
final class LogStatsTabProvider implements DashboardTabProviderInterface
{
    public const SECURITY_CONTEXT = 'sulu.settings.log_stats';

    public const VIEW_TYPE = 'amoifr_log_stats.dashboard';

    public const API_ROUTE = 'amoifr_log_stats.dashboard_api';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $timezone,
    ) {
    }

    public function getKey(): string
    {
        return 'visitors';
    }

    public function getTitle(): string
    {
        return 'sulu_dashboard.visitors';
    }

    public function getViewType(): string
    {
        return self::VIEW_TYPE;
    }

    public function getViewOptions(): array
    {
        return ['apiUrl' => $this->apiUrl(), 'timezone' => $this->timezone];
    }

    public function getSecurityContext(): string
    {
        return self::SECURITY_CONTEXT;
    }

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Null when the application did not import the routes of the bundle: the view then says so.
     */
    public function apiUrl(): ?string
    {
        try {
            return $this->urlGenerator->generate(self::API_ROUTE);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
