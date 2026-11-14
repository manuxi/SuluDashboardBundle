<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Functional;

use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\TestBundle\Testing\KernelTestCase;
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
            ['sulu_activities', 'sulu_articles', 'sulu_media', 'sulu_open_items', 'sulu_pages', 'sulu_snippets', 'sulu_translations'],
            $keys,
        );
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

        $this->assertSame('/', $registry->findViewByName('sulu_dashboard.dashboard')->getPath());
        $this->assertSame('/search', $registry->findViewByName('sulu_search.search')->getPath());

        $onRoot = array_filter($registry->getViews(), static fn ($view): bool => '/' === $view->getPath());
        $this->assertCount(1, $onRoot);
    }
}
