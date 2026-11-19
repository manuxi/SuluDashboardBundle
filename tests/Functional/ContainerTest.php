<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Functional;

use Manuxi\SuluDashboardBundle\Tab\TabRegistry;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\TestBundle\Testing\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Routing\RouterInterface;

/**
 * Boots the admin kernel with all Sulu bundles: the services are wired correctly, the compiler pass collected the
 * built-in tiles, and the route exists.
 */
class ContainerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testTheBuiltinTilesAreCollectedByTheCompilerPass(): void
    {
        /** @var WidgetRegistry $registry */
        $registry = self::getContainer()->get('sulu_dashboard.widget_registry');

        $keys = array_keys($registry->all());
        sort($keys);

        $this->assertSame(
            [
                'sulu_activities', 'sulu_articles', 'sulu_media', 'sulu_open_items', 'sulu_pages', 'sulu_seo', 'sulu_snippets',
                'sulu_system', 'sulu_system_environment', 'sulu_system_failed_messages', 'sulu_system_last_check', 'sulu_system_migrations',
                'sulu_system_storage', 'sulu_system_versions', 'sulu_system_vulnerabilities',
                'sulu_translations',
            ],
            $keys,
        );
    }

    /**
     * The tiles of "System" with their real services (cache pool, optional migrations and Messenger): they render in a
     * booted kernel without any result of the console command and report that the check never ran.
     */
    public function testTheSystemTilesRenderWithTheRealServices(): void
    {
        /** @var WidgetRegistry $registry */
        $registry = self::getContainer()->get('sulu_dashboard.widget_registry');

        $titles = array_map(static fn (array $row): mixed => $row['title'], $registry->all()['sulu_system']->getData('en')['rows']);
        $this->assertContains('sulu_dashboard.system_not_run', $titles);
        $this->assertSame('sulu_dashboard.tab_system', $registry->all()['sulu_system']->getData('en')['footer']['rows'][0]['link']['view']);

        $this->assertSame('never', $registry->all()['sulu_system_vulnerabilities']->getData('en')['status']);
        $versions = array_map(static fn (array $row): mixed => $row['title'], $registry->all()['sulu_system_versions']->getData('en')['rows']);
        $this->assertContains('PHP', $versions);
    }

    public function testTheTabSystemIsRegisteredWithItsTilesAndOnlyForTheSystemPermission(): void
    {
        /** @var TabRegistry $tabs */
        $tabs = self::getContainer()->get('sulu_dashboard.tab_registry');
        $tab = $tabs->all()['system'];

        $this->assertSame('sulu.settings.dashboard_system', $tab->getSecurityContext());

        /** @var WidgetRegistry $registry */
        $registry = self::getContainer()->get('sulu_dashboard.widget_registry');
        $keys = array_map(static fn ($widget): string => $widget->getKey(), $registry->available('system'));
        $this->assertContains('sulu_system_vulnerabilities', $keys);
        $this->assertNotContains('sulu_system', $keys, 'the small tile belongs to the overview');
    }

    public function testTheTabExtensionsIsRegisteredButHiddenWithoutTilesOfOtherBundles(): void
    {
        /** @var TabRegistry $tabs */
        $tabs = self::getContainer()->get('sulu_dashboard.tab_registry');

        $this->assertArrayHasKey('extensions', $tabs->all());
        $this->assertNotContains('extensions', array_map(static fn ($tab): string => $tab->getKey(), $tabs->available()));

        /** @var WidgetRegistry $registry */
        $registry = self::getContainer()->get('sulu_dashboard.widget_registry');
        $this->assertSame([], $registry->available('extensions'), 'the test kernel brings no tiles of other bundles');
        $this->assertNotNull($registry->find('sulu_pages'), 'the built-in tiles stay on the overview');
    }

    public function testTheCommandsAreRegistered(): void
    {
        $application = new Application(self::$kernel);

        $this->assertTrue($application->has('sulu:dashboard:system-check'));
        $this->assertTrue($application->has('sulu:dashboard:dump'));
    }

    public function testTheWidgetRouteIsRegistered(): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        $this->assertSame('/admin/api/dashboard/widgets/sulu_pages', $router->generate('sulu_dashboard.get_widget', ['key' => 'sulu_pages']));
    }

    /**
     * With the real order of the admin classes: the dashboard owns "/" and no other view shares the path.
     */
    public function testTheDashboardOwnsTheRootPathAndTheSearchMovedAway(): void
    {
        /** @var ViewRegistry $registry */
        $registry = self::getContainer()->get('sulu_admin.view_registry');

        // with a tab (the tab "System") the tab view owns "/" and the overview is its first child
        $this->assertSame('/', $registry->findViewByName('sulu_dashboard.tabs')->getPath());
        $this->assertSame('/overview', $registry->findViewByName('sulu_dashboard.dashboard')->getPath());
        $this->assertSame('/search', $registry->findViewByName('sulu_search.search')->getPath());

        $onRoot = array_filter($registry->getViews(), static fn ($view): bool => '/' === $view->getPath());
        $this->assertCount(1, $onRoot);
    }
}
