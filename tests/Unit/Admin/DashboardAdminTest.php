<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Admin;

use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\ArrayWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

class DashboardAdminTest extends TestCase
{
    /**
     * @param list<string>      $allowed security contexts the user may view
     * @param list<ArrayWidget> $widgets
     */
    private function admin(array $allowed, array $widgets = [], bool $startPage = true, bool $balance = true): DashboardAdmin
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(
            static fn (mixed $subject, string $permission): bool => 'view' === $permission && \in_array($subject, $allowed, true),
        );

        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $localizations->method('getLocales')->willReturn(['de', 'en']);

        $form = new FormMetadata();
        $form->setKey('blog');
        $form->setTitles(['de' => 'Blog', 'en' => 'Blog post']);
        $typed = new TypedFormMetadata();
        $typed->addForm('blog', $form);

        $provider = $this->createMock(MetadataProviderInterface::class);
        $provider->method('getMetadata')->willReturnCallback(static fn (string $key) => 'article' === $key ? $typed : new TypedFormMetadata());
        $registry = $this->createMock(MetadataProviderRegistry::class);
        $registry->method('getMetadataProvider')->willReturn($provider);

        return new DashboardAdmin(new ViewBuilderFactory(), $checker, $localizations, new WidgetRegistry($widgets, $checker), $registry, $startPage, null, true, $balance);
    }

    /**
     * A view like the one of Sulu's SearchAdmin, registered before the dashboard.
     */
    private function viewsWithSearch(): ViewCollection
    {
        $factory = new ViewBuilderFactory();
        $views = new ViewCollection();
        $views->add($factory->createViewBuilder('sulu_search.search', '/', 'sulu_search.search')->setOption('hint', 'kept'));

        return $views;
    }

    public function testStartPageMovesTheSearchToSearch(): void
    {
        $views = $this->viewsWithSearch();

        $this->admin([DashboardAdmin::SECURITY_CONTEXT])->configureViews($views);

        $dashboard = $views->get(DashboardAdmin::VIEW)->getView();
        $search = $views->get('sulu_search.search')->getView();
        $this->assertSame('/', $dashboard->getPath());
        $this->assertSame('/search', $search->getPath());
        $this->assertSame('sulu_search.search', $search->getType());
        $this->assertSame('kept', $search->getOption('hint'), 'options of the old view are kept');
        $this->assertNull($search->getParent());
    }

    public function testWithoutStartPageTheDashboardIsOnDashboardAndTheSearchStays(): void
    {
        $views = $this->viewsWithSearch();

        $this->admin([DashboardAdmin::SECURITY_CONTEXT], [], false)->configureViews($views);

        $this->assertSame('/dashboard', $views->get(DashboardAdmin::VIEW)->getView()->getPath());
        $this->assertSame('/', $views->get('sulu_search.search')->getView()->getPath());
    }

    public function testWithoutAPermissionTheSearchStaysOnTheRootPath(): void
    {
        $views = $this->viewsWithSearch();

        $this->admin([])->configureViews($views);

        $this->assertSame('/', $views->get('sulu_search.search')->getView()->getPath());
        $this->assertFalse($views->has(DashboardAdmin::VIEW));
    }

    public function testWorksWithoutTheSearchView(): void
    {
        $views = new ViewCollection();

        $this->admin([DashboardAdmin::SECURITY_CONTEXT])->configureViews($views);

        $this->assertSame('/', $views->get(DashboardAdmin::VIEW)->getView()->getPath());
        $this->assertFalse($views->has('sulu_search.search'));
    }

    public function testRunsAfterTheOtherAdminClasses(): void
    {
        $this->assertLessThan(0, DashboardAdmin::getPriority());
    }

    public function testNothingChangesWithoutThePermission(): void
    {
        $admin = $this->admin([]);
        $views = new ViewCollection();
        $navigation = new NavigationItemCollection();

        $admin->configureViews($views);
        $admin->configureNavigationItems($navigation);

        $this->assertCount(0, $views->all());
        $this->assertCount(0, $navigation->all());
    }

    public function testViewOnTheRootPathWithLocalesAndPermittedTilesOnly(): void
    {
        $admin = $this->admin(
            [DashboardAdmin::SECURITY_CONTEXT, 'sulu.visible'],
            [
                new ArrayWidget('visible', securityContext: 'sulu.visible', priority: 5, title: 'a.visible'),
                new ArrayWidget('hidden', securityContext: 'sulu.hidden'),
                new ArrayWidget('first', priority: 9, pinned: true),
            ],
        );
        $views = new ViewCollection();

        $admin->configureViews($views);

        $view = $views->get(DashboardAdmin::VIEW)->getView();
        $this->assertSame('/', $view->getPath());
        $this->assertSame(DashboardAdmin::VIEW, $view->getType());
        $this->assertSame(['de', 'en'], $view->getOption('locales'));
        $this->assertSame(
            ['pages' => [], 'articles' => ['blog' => ['de' => 'Blog', 'en' => 'Blog post']]],
            $view->getOption('templateTitles'),
        );
        $this->assertSame(
            [
                ['key' => 'first', 'title' => 'test.title', 'type' => 'number', 'column' => 'main', 'pinned' => true],
                ['key' => 'visible', 'title' => 'a.visible', 'type' => 'number', 'column' => 'main', 'pinned' => false],
            ],
            $view->getOption('widgets'),
        );
    }

    public function testTheViewKnowsWhetherTheColumnsAreBalanced(): void
    {
        foreach ([true, false] as $balance) {
            $views = new ViewCollection();
            $this->admin([DashboardAdmin::SECURITY_CONTEXT], [], true, $balance)->configureViews($views);

            $this->assertSame($balance, $views->get(DashboardAdmin::VIEW)->getView()->getOption('balance'));
        }
    }

    public function testNavigationItemIsTheFirstOne(): void
    {
        $admin = $this->admin([DashboardAdmin::SECURITY_CONTEXT]);
        $navigation = new NavigationItemCollection();

        $admin->configureNavigationItems($navigation);

        $item = $navigation->get(DashboardAdmin::NAVIGATION_ITEM);
        $this->assertSame(1, $item->getPosition());
        $this->assertSame(DashboardAdmin::VIEW, $item->getView());
    }

    public function testRegistersTheViewContextInTheSettingsGroup(): void
    {
        $contexts = $this->admin([])->getSecurityContexts();

        $this->assertSame(['view'], $contexts['Sulu']['Settings'][DashboardAdmin::SECURITY_CONTEXT]);
    }
}
