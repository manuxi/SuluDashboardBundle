<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Integration;

use InvalidArgumentException;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\DependencyInjection\SuluDashboardExtension;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTabProvider;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTilesTabProvider;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTileWidget;
use Manuxi\SuluDashboardBundle\Integration\LogStats\VisitorsWidget;
use Manuxi\SuluDashboardBundle\Tab\RemovesReplacedViewsInterface;
use Manuxi\SuluDashboardBundle\Tab\TilesTabProviderInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
        $this->assertSame(['apiUrl' => '/admin/api/log-stats', 'timezone' => 'Europe/Berlin', 'days' => 7], $widget->getData('de'));
    }

    public function testTheTilesTabIsATabOfTilesOfTheGroupVisitors(): void
    {
        $tab = new LogStatsTilesTabProvider($this->createMock(UrlGeneratorInterface::class), 'Europe/Berlin');

        $this->assertInstanceOf(TilesTabProviderInterface::class, $tab);
        $this->assertInstanceOf(RemovesReplacedViewsInterface::class, $tab);
        $this->assertSame('visitors', $tab->getKey());
        $this->assertSame(DashboardAdmin::VIEW, $tab->getViewType());
        $this->assertSame('visitors', $tab->getGroup());
        $this->assertSame([7, 30, 90, 365], $tab->getPeriods());
        $this->assertSame(30, $tab->getDefaultPeriod());
        $this->assertFalse($tab->showLocale());
        $this->assertSame('sulu.settings.log_stats', $tab->getSecurityContext());
        $this->assertSame('amoifr_log_stats.statistics', $tab->getReplacedNavigationItem());
    }

    public function testTheViewTabIsNotATabOfTiles(): void
    {
        $tab = $this->provider('/admin/api/log-stats');

        $this->assertNotInstanceOf(TilesTabProviderInterface::class, $tab);
        $this->assertNotInstanceOf(RemovesReplacedViewsInterface::class, $tab);
    }

    public function testEveryTileIsOfTheGroupAndHasAKnownPlace(): void
    {
        $provider = $this->provider('/admin/api/log-stats');
        $keys = [];

        foreach (array_keys(LogStatsTileWidget::TILES) as $metric) {
            $widget = new LogStatsTileWidget($provider, $metric);
            $keys[] = $widget->getKey();

            $this->assertSame('visitors', $widget->getGroup());
            $this->assertSame(WidgetTypes::CUSTOM, $widget->getType());
            $this->assertContains($widget->getColumn(), WidgetColumns::all());
            $this->assertSame('sulu.settings.log_stats', $widget->getSecurityContext());
            $this->assertSame(['metric' => $metric, 'apiUrl' => '/admin/api/log-stats', 'timezone' => 'Europe/Berlin'], $widget->getData('de'));
            $this->assertSame('sulu_visitors_' . $metric, $widget->getKey());
        }

        $this->assertSame($keys, array_unique($keys));
        $this->assertNotContains('sulu_visitors', $keys, 'the tile of the overview keeps its key');
    }

    public function testAnUnknownMetricIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LogStatsTileWidget($this->provider('/x'), 'nope');
    }

    public function testTheTilesAreRegisteredInTheModeTilesOnly(): void
    {
        $register = new ReflectionMethod(SuluDashboardExtension::class, 'loadLogStatsTiles');

        foreach (['tiles' => true, 'view' => false] as $mode => $tiles) {
            $container = new ContainerBuilder();
            $container->setDefinition(LogStatsTabProvider::class, new Definition(LogStatsTabProvider::class));
            $register->invoke(new SuluDashboardExtension(), $container, (string) $mode);

            $this->assertSame($tiles, $container->has('sulu_visitors_chart_views'), (string) $mode);
            $this->assertSame($tiles ? LogStatsTilesTabProvider::class : LogStatsTabProvider::class, $container->getDefinition(LogStatsTabProvider::class)->getClass(), (string) $mode);
            $this->assertCount($tiles ? \count(LogStatsTileWidget::TILES) : 0, array_filter($container->getDefinitions(), static fn (Definition $definition): bool => $definition->hasTag('sulu_dashboard.widget')), (string) $mode);
        }
    }

    public function testTheModeComesFromTheConfigurationAndDefaultsToTiles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        (new SuluDashboardExtension())->load([], $container);
        $this->assertSame('tiles', $container->getParameter('sulu_dashboard.log_stats.tab'));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        (new SuluDashboardExtension())->load([['log_stats' => ['tab' => 'view']]], $container);
        $this->assertSame('view', $container->getParameter('sulu_dashboard.log_stats.tab'));
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
