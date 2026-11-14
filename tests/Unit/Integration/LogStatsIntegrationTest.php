<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Integration;

use Manuxi\SuluDashboardBundle\DependencyInjection\SuluDashboardExtension;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTabProvider;
use Manuxi\SuluDashboardBundle\Integration\LogStats\VisitorsWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The integration needs nothing of the log statistics bundle: it is tested here without that bundle installed.
 */
class LogStatsIntegrationTest extends TestCase
{
    private function provider(?string $url): LogStatsTabProvider
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        if (null === $url) {
            $generator->method('generate')->willThrowException(new RouteNotFoundException());
        } else {
            $generator->method('generate')->with('amoifr_log_stats.dashboard_api')->willReturn($url);
        }

        return new LogStatsTabProvider($generator, 'Europe/Berlin');
    }

    public function testTheTabUsesTheViewAndTheOptionsOfTheLogStatisticsBundle(): void
    {
        $tab = $this->provider('/admin/api/log-stats');

        $this->assertSame('visitors', $tab->getKey());
        $this->assertSame('amoifr_log_stats.dashboard', $tab->getViewType());
        $this->assertSame(['apiUrl' => '/admin/api/log-stats', 'timezone' => 'Europe/Berlin'], $tab->getViewOptions());
        $this->assertSame('sulu.settings.log_stats', $tab->getSecurityContext());
    }

    public function testTheTabReplacesTheStatisticsMenuItemAndKeepsItsView(): void
    {
        $tab = $this->provider('/admin/api/log-stats');

        $this->assertSame('amoifr_log_stats.statistics', $tab->getReplacedNavigationItem());
        $this->assertSame(['amoifr_log_stats.dashboard'], $tab->getReplacedViews());
    }

    public function testWithoutImportedRoutesTheViewGetsNullAndSaysSo(): void
    {
        $this->assertNull($this->provider(null)->getViewOptions()['apiUrl']);
    }

    public function testTheVisitorsTileReadsTheApiOfTheLogStatisticsWithTheSamePermission(): void
    {
        $widget = new VisitorsWidget($this->provider('/admin/api/log-stats'));

        $this->assertSame('sulu_visitors', $widget->getKey());
        $this->assertSame(WidgetTypes::CUSTOM, $widget->getType());
        $this->assertSame(WidgetColumns::SIDE, $widget->getColumn());
        $this->assertSame('sulu.settings.log_stats', $widget->getSecurityContext());
        $this->assertSame(['apiUrl' => '/admin/api/log-stats', 'days' => 7], $widget->getData('de'));
    }

    public function testNothingOfTheIntegrationIsLoadedWithoutTheLogStatisticsBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SuluDashboardBundle' => 'Manuxi\SuluDashboardBundle\SuluDashboardBundle']);

        (new SuluDashboardExtension())->load([], $container);

        $this->assertFalse($container->has(LogStatsTabProvider::class));
        $this->assertFalse($container->has(VisitorsWidget::class));
        $this->assertTrue($container->has('sulu_dashboard.tab_registry'));
    }
}
