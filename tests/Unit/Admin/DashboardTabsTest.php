<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Admin;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;
use Manuxi\SuluDashboardBundle\Tab\TabRegistry;
use Manuxi\SuluDashboardBundle\Tests\Unit\Tab\Fixtures\ArrayTab;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\AdminPool;
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
use Throwable;

/**
 * The dashboard as a tab view when a bundle brings a tab. The paths are checked the way Sulu's ViewRegistry builds them
 * (path of the parent in front of the path of the child), together with a view on "/" like the one of SearchAdmin.
 */
class DashboardTabsTest extends TestCase
{
    /**
     * @param list<DashboardTabProviderInterface> $tabs
     * @param list<string>                        $allowed
     */
    private function admin(array $tabs, bool $startPage, array $allowed = [DashboardAdmin::SECURITY_CONTEXT, 'sulu.tab']): DashboardAdmin
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(
            static fn (mixed $subject, string $permission): bool => 'view' === $permission && \in_array($subject, $allowed, true),
        );
        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $localizations->method('getLocales')->willReturn(['de']);
        $provider = $this->createMock(MetadataProviderInterface::class);
        $provider->method('getMetadata')->willReturn(new TypedFormMetadata());
        $metadata = $this->createMock(MetadataProviderRegistry::class);
        $metadata->method('getMetadataProvider')->willReturn($provider);

        return new DashboardAdmin(new ViewBuilderFactory(), $checker, $localizations, new WidgetRegistry([], $checker), $metadata, $startPage, new TabRegistry($tabs, $checker));
    }

    private function registry(DashboardAdmin $dashboard): ViewRegistry
    {
        $search = new class extends Admin implements ViewProviderInterface {
            public function configureViews(ViewCollection $viewCollection): void
            {
                $viewCollection->add((new ViewBuilderFactory())->createViewBuilder('sulu_search.search', '/', 'sulu_search.search'));
            }
        };

        // the dashboard runs last, like with its priority
        return new ViewRegistry(new AdminPool([$search, $dashboard]));
    }

    /**
     * @return iterable<string, array{bool, string, string, string}>
     */
    public static function startPages(): iterable
    {
        yield 'dashboard on the start page' => [true, '/', '/overview', '/visitors'];
        yield 'dashboard on /dashboard' => [false, '/dashboard', '/dashboard/overview', '/dashboard/visitors'];
    }

    #[DataProvider('startPages')]
    public function testTabsGetCleanPaths(bool $startPage, string $parent, string $overview, string $visitors): void
    {
        $registry = $this->registry($this->admin([new ArrayTab('visitors', securityContext: 'sulu.tab')], $startPage));

        $this->assertSame($parent, $registry->findViewByName(DashboardAdmin::TABS_VIEW)->getPath());
        $this->assertSame($overview, $registry->findViewByName(DashboardAdmin::VIEW)->getPath());
        $this->assertSame($visitors, $registry->findViewByName('sulu_dashboard.tab_visitors')->getPath());

        $paths = array_map(static fn ($view): string => $view->getPath(), $registry->getViews());
        $this->assertNotContains('//overview', $paths);
        $this->assertSame(array_values(array_unique($paths)), array_values($paths), 'no path is used twice');
    }

    public function testStartPageKeepsTheSearchOnSearchAndTheTabViewOnTheRoot(): void
    {
        $registry = $this->registry($this->admin([new ArrayTab('visitors', securityContext: 'sulu.tab')], true));

        $this->assertSame('/search', $registry->findViewByName('sulu_search.search')->getPath());
    }

    public function testTheTabViewsCarryTitleOrderAndTheOptionsOfTheProvider(): void
    {
        $registry = $this->registry($this->admin([new ArrayTab('visitors', title: 'app.visitors', viewType: 'amoifr_log_stats.dashboard', options: ['apiUrl' => '/admin/api/log-stats', 'timezone' => 'Europe/Berlin'], securityContext: 'sulu.tab')], true));

        $overview = $registry->findViewByName(DashboardAdmin::VIEW);
        $tab = $registry->findViewByName('sulu_dashboard.tab_visitors');

        $this->assertSame(DashboardAdmin::TABS_VIEW, $overview->getParent());
        $this->assertSame('sulu_dashboard.overview', $overview->getOption('tabTitle'));
        $this->assertSame(1, $overview->getOption('tabPriority'));
        $this->assertSame('amoifr_log_stats.dashboard', $tab->getType());
        $this->assertSame('app.visitors', $tab->getOption('tabTitle'));
        $this->assertSame('/admin/api/log-stats', $tab->getOption('apiUrl'));
        $this->assertSame('Europe/Berlin', $tab->getOption('timezone'));
        $this->assertSame(DashboardAdmin::TABS_VIEW, $tab->getParent());
    }

    public function testWithoutAVisibleTabTheDashboardIsASimpleView(): void
    {
        // the user lacks the permission of the only tab
        $registry = $this->registry($this->admin([new ArrayTab('visitors', securityContext: 'sulu.other')], true));

        $this->assertSame('/', $registry->findViewByName(DashboardAdmin::VIEW)->getPath());
        $this->assertNull($registry->findViewByName(DashboardAdmin::VIEW)->getParent());
        $this->expectException(Throwable::class);
        $registry->findViewByName(DashboardAdmin::TABS_VIEW);
    }

    public function testWithoutAnyTabProviderTheDashboardIsASimpleView(): void
    {
        $admin = $this->admin([], false);
        $registry = $this->registry($admin);

        $this->assertSame('/dashboard', $registry->findViewByName(DashboardAdmin::VIEW)->getPath());

        $navigation = new NavigationItemCollection();
        $admin->configureNavigationItems($navigation);
        $this->assertSame(DashboardAdmin::VIEW, $navigation->get(DashboardAdmin::NAVIGATION_ITEM)->getView());
    }

    public function testTheMenuItemOpensTheTabViewWhenThereAreTabs(): void
    {
        $admin = $this->admin([new ArrayTab('visitors', securityContext: 'sulu.tab')], true);
        $navigation = new NavigationItemCollection();

        $admin->configureNavigationItems($navigation);

        $this->assertSame(DashboardAdmin::TABS_VIEW, $navigation->get(DashboardAdmin::NAVIGATION_ITEM)->getView());
    }
}
