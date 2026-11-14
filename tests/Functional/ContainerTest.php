<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Functional;

use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
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
}
