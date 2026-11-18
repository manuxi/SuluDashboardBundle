<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Admin;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTabProvider;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTilesTabProvider;
use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;
use Manuxi\SuluDashboardBundle\Tab\TabRegistry;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\AdminPool;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewProviderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The tab "Visitors" made of tiles takes the page of the log statistics away: the view leaves the collection and its path
 * redirects to the tab. The tab that shows the page itself (mode "view") leaves the view alone.
 */
class DashboardRemovedViewsTest extends TestCase
{
    private function url(): UrlGeneratorInterface
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturn('/admin/api/log-stats');

        return $generator;
    }

    private function admin(DashboardTabProviderInterface $tab, bool $replaceNavigation = true): DashboardAdmin
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturn(true);
        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $localizations->method('getLocales')->willReturn(['de']);
        $provider = $this->createMock(MetadataProviderInterface::class);
        $provider->method('getMetadata')->willReturn(new TypedFormMetadata());
        $metadata = $this->createMock(MetadataProviderRegistry::class);
        $metadata->method('getMetadataProvider')->willReturn($provider);

        return new DashboardAdmin(new ViewBuilderFactory(), $checker, $localizations, new WidgetRegistry([], $checker), $metadata, true, new TabRegistry([$tab], $checker), $replaceNavigation);
    }

    private function registry(DashboardAdmin $dashboard): ViewRegistry
    {
        // the admin class of the log statistics: its view on /log-stats
        $logStats = new class extends Admin implements ViewProviderInterface {
            public function configureViews(ViewCollection $viewCollection): void
            {
                $viewCollection->add((new ViewBuilderFactory())->createViewBuilder('amoifr_log_stats.dashboard', '/log-stats', 'amoifr_log_stats.dashboard'));
            }
        };

        return new ViewRegistry(new AdminPool([$logStats, $dashboard]));
    }

    public function testTheTilesTabRemovesTheViewAndRedirectsItsPathToTheTab(): void
    {
        $registry = $this->registry($this->admin(new LogStatsTilesTabProvider($this->url(), 'Europe/Berlin')));

        $names = array_map(static fn ($view): string => $view->getName(), $registry->getViews());
        $this->assertNotContains('amoifr_log_stats.dashboard', $names);

        $redirect = $registry->findViewByName('sulu_dashboard.redirect.amoifr_log_stats.dashboard');
        $this->assertSame('/log-stats', $redirect->getPath());
        $this->assertSame(DashboardAdmin::REDIRECT_VIEW_TYPE, $redirect->getType());
        $this->assertSame('sulu_dashboard.tab_visitors', $redirect->getOption('redirectTo'));
        // the target exists
        $this->assertSame('/visitors', $registry->findViewByName('sulu_dashboard.tab_visitors')->getPath());
    }

    public function testTheMenuItemOfTheDashboardStaysLitOnTheRedirect(): void
    {
        $navigation = new NavigationItemCollection();
        $navigation->add(new NavigationItem('amoifr_log_stats.statistics'));
        $this->admin(new LogStatsTilesTabProvider($this->url(), 'UTC'))->configureNavigationItems($navigation);

        $this->assertFalse($navigation->has('amoifr_log_stats.statistics'));
        $this->assertContains('sulu_dashboard.redirect.amoifr_log_stats.dashboard', $navigation->get(DashboardAdmin::NAVIGATION_ITEM)->getChildViews());
    }

    public function testTheViewModeLeavesTheViewAlone(): void
    {
        $registry = $this->registry($this->admin(new LogStatsTabProvider($this->url(), 'UTC')));

        $this->assertSame('/log-stats', $registry->findViewByName('amoifr_log_stats.dashboard')->getPath());
        $this->assertSame('amoifr_log_stats.dashboard', $registry->findViewByName('sulu_dashboard.tab_visitors')->getType());
    }

    public function testWithoutReplacingTheMenuItemTheViewStaysToo(): void
    {
        $registry = $this->registry($this->admin(new LogStatsTilesTabProvider($this->url(), 'UTC'), false));

        $this->assertSame('/log-stats', $registry->findViewByName('amoifr_log_stats.dashboard')->getPath());
    }
}
