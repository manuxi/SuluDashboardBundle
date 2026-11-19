<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget;

use Acme\DashboardFixtures\ForeignService;
use Acme\DashboardFixtures\ForeignWidget;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardTabPass;
use Manuxi\SuluDashboardBundle\DependencyInjection\Configuration;
use Manuxi\SuluDashboardBundle\DependencyInjection\SuluDashboardExtension;
use Manuxi\SuluDashboardBundle\Tab\TabRegistry;
use Manuxi\SuluDashboardBundle\Tests\Unit\Tab\Fixtures\TilesTab;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\ArrayWidget;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\GroupedWidget;
use Manuxi\SuluDashboardBundle\Widget\Extensions\ExtensionsTabProvider;
use Manuxi\SuluDashboardBundle\Widget\System\SystemTabProvider;
use Manuxi\SuluDashboardBundle\Widget\TaggedWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use Manuxi\SuluDashboardBundleExtra\LookalikeWidget;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The tab "Extensions": which tiles go there, when the tab exists, how it is configured and where it stands.
 */
class ExtensionsTest extends TestCase
{
    /**
     * @param list<string> $allowed
     */
    private function checker(array $allowed = []): SecurityCheckerInterface
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(
            static fn (mixed $subject, string $permission): bool => 'view' === $permission && \in_array($subject, $allowed, true),
        );

        return $checker;
    }

    /**
     * @param list<string> $keep
     * @param list<string> $allowed
     */
    private function registry(bool $extensions = true, array $keep = [], array $allowed = []): WidgetRegistry
    {
        return new WidgetRegistry([
            new ArrayWidget('pages'),
            new ArrayWidget('open_items'),
            new GroupedWidget('traffic', 'visitors'),
            new ForeignWidget('ratings'),
            new ForeignWidget('glossary'),
            new ForeignWidget('secret', 'sulu.secret'),
            new LookalikeWidget('lookalike'),
            new TaggedWidget(new ForeignService(), ['key' => 'tagged', 'title' => 'x', 'type' => 'number']),
        ], $this->checker($allowed), [], $extensions, $keep);
    }

    /**
     * @param list<\Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface> $widgets
     *
     * @return list<string>
     */
    private function keys(array $widgets): array
    {
        $keys = array_map(static fn ($widget): string => $widget->getKey(), $widgets);
        sort($keys);

        return $keys;
    }

    public function testTilesOfOtherBundlesGoToTheExtensionsAndTheBuiltInOnesStayOnTheOverview(): void
    {
        $registry = $this->registry();

        $this->assertSame(['open_items', 'pages'], $this->keys($registry->available()));
        $this->assertSame(['glossary', 'lookalike', 'ratings', 'tagged'], $this->keys($registry->available(ExtensionsTabProvider::GROUP)));
    }

    public function testATileWithAGroupOfItsOwnKeepsIt(): void
    {
        $registry = $this->registry();

        $this->assertSame(['traffic'], $this->keys($registry->available('visitors')));
        $this->assertNotContains('traffic', $this->keys($registry->available(ExtensionsTabProvider::GROUP)));
    }

    public function testANamespaceThatOnlyStartsLikeOursIsForeign(): void
    {
        $this->assertTrue(WidgetRegistry::isForeign(new LookalikeWidget('x')));
        $this->assertFalse(WidgetRegistry::isForeign(new ArrayWidget('x')));
    }

    public function testATaggedServiceIsJudgedByTheClassOfTheService(): void
    {
        $this->assertTrue(WidgetRegistry::isForeign(new TaggedWidget(new ForeignService(), [])));
        $this->assertFalse(WidgetRegistry::isForeign(new TaggedWidget(new ArrayWidget('x'), [])));
    }

    public function testKeysInKeepOnOverviewStayOnTheOverview(): void
    {
        $registry = $this->registry(keep: ['ratings']);

        $this->assertSame(['open_items', 'pages', 'ratings'], $this->keys($registry->available()));
        $this->assertNotContains('ratings', $this->keys($registry->available(ExtensionsTabProvider::GROUP)));
    }

    public function testWithoutTheTabEverythingStaysOnTheOverview(): void
    {
        $registry = $this->registry(extensions: false);

        $this->assertSame(['glossary', 'lookalike', 'open_items', 'pages', 'ratings', 'tagged'], $this->keys($registry->available()));
        $this->assertSame([], $registry->available(ExtensionsTabProvider::GROUP));
    }

    public function testTheTilesOfTheTabFollowThePermissions(): void
    {
        $this->assertNotContains('secret', $this->keys($this->registry()->available(ExtensionsTabProvider::GROUP)));
        $this->assertContains('secret', $this->keys($this->registry(allowed: ['sulu.secret'])->available(ExtensionsTabProvider::GROUP)));
        $this->assertNull($this->registry()->find('secret'));
    }

    public function testTheTabExistsOnlyWhileTheUserSeesATile(): void
    {
        $withTiles = new ExtensionsTabProvider($this->registry());
        $this->assertTrue($withTiles->isAvailable());

        $none = new ExtensionsTabProvider(new WidgetRegistry([new ArrayWidget('pages')], $this->checker()));
        $this->assertFalse($none->isAvailable(), 'no bundle tiles, no tab');

        $onlySecret = new ExtensionsTabProvider(new WidgetRegistry([new ForeignWidget('secret', 'sulu.secret')], $this->checker()));
        $this->assertFalse($onlySecret->isAvailable(), 'the user may not see the only tile');

        $registry = new TabRegistry([$onlySecret, new TilesTab()], $this->checker());
        $this->assertSame(['visitors'], array_map(static fn ($tab): string => $tab->getKey(), $registry->available()));

        $allowed = new WidgetRegistry([new ForeignWidget('secret', 'sulu.secret')], $this->checker(['sulu.secret']));
        $registry = new TabRegistry([new ExtensionsTabProvider($allowed)], $this->checker(['sulu.secret']));
        $this->assertSame(['extensions'], array_map(static fn ($tab): string => $tab->getKey(), $registry->available()));
    }

    public function testTheTabHasItsOwnSettingKeyTheLanguageSelectAndNoPeriod(): void
    {
        $views = $this->views($this->registry());
        $view = $views->get('sulu_dashboard.tab_extensions')->getView();

        $this->assertSame(DashboardAdmin::VIEW, $view->getType());
        $this->assertSame('sulu_dashboard.layout.extensions', $view->getOption('settingKey'));
        $this->assertTrue($view->getOption('showLocale'));
        $this->assertNull($view->getOption('period'));
        $this->assertSame('sulu_dashboard.extensions', $view->getOption('tabTitle'));
        $this->assertSame(['glossary', 'lookalike', 'ratings', 'tagged'], $this->keys(array_map(
            static fn (array $widget): object => new ArrayWidget($widget['key']),
            $view->getOption('widgets'),
        )));
    }

    public function testTheOverviewLosesTheTilesOfTheExtensions(): void
    {
        $overview = $this->views($this->registry())->get(DashboardAdmin::VIEW)->getView();

        $this->assertSame(['open_items', 'pages'], $this->keys(array_map(
            static fn (array $widget): object => new ArrayWidget($widget['key']),
            $overview->getOption('widgets'),
        )));
    }

    public function testTheTabsComeInTheOrderOverviewExtensionsVisitorsSystem(): void
    {
        $views = $this->views($this->registry());

        $order = [];
        foreach (['overview' => DashboardAdmin::VIEW, 'extensions' => 'sulu_dashboard.tab_extensions', 'visitors' => 'sulu_dashboard.tab_visitors', 'system' => 'sulu_dashboard.tab_system'] as $name => $view) {
            $order[$name] = $views->get($view)->getView()->getOption('tabOrder');
        }
        asort($order);

        $this->assertSame(['overview', 'extensions', 'visitors', 'system'], array_keys($order));
    }

    public function testWithoutExtensionsTheTabIsMissingAndTheOverviewKeepsEverything(): void
    {
        $registry = $this->registry(extensions: false);
        $views = $this->views($registry, new TabRegistry([new TilesTab()], $this->checker()));

        $this->assertFalse($views->has('sulu_dashboard.tab_extensions'));
        $this->assertContains('ratings', array_map(static fn (array $widget): string => $widget['key'], $views->get(DashboardAdmin::VIEW)->getView()->getOption('widgets')));
    }

    public function testTheConfigurationDefaultsToTheTab(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        $this->assertSame(['tab' => true, 'keep_on_overview' => []], $config['extensions']);
        $this->assertFalse($config['log_stats']['overview_tile']);

        $config = (new Processor())->processConfiguration(new Configuration(), [['extensions' => ['tab' => false, 'keep_on_overview' => ['ratings']], 'log_stats' => ['overview_tile' => true]]]);
        $this->assertSame(['tab' => false, 'keep_on_overview' => ['ratings']], $config['extensions']);
        $this->assertTrue($config['log_stats']['overview_tile']);
    }

    public function testTheExtensionRegistersTheTabProviderOnlyWhenTheTabIsOn(): void
    {
        foreach ([[[], true], [[['extensions' => ['tab' => false]]], false]] as [$configs, $registered]) {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.bundles', []);
            (new SuluDashboardExtension())->load($configs, $container);

            $this->assertSame($registered, $container->has(ExtensionsTabProvider::class));
            $this->assertSame($registered, $container->hasDefinition(ExtensionsTabProvider::class) && $container->getDefinition(ExtensionsTabProvider::class)->hasTag(DashboardTabPass::TAG));
            $this->assertSame($registered, $container->getParameter('sulu_dashboard.extensions.tab'));
        }
    }

    public function testTheOtherTabsKeepTheirPriorityBehindTheExtensions(): void
    {
        $extensions = new ExtensionsTabProvider($this->registry());

        $this->assertGreaterThan((new TilesTab())->getPriority(), $extensions->getPriority());
        $this->assertGreaterThan((new SystemTabProvider())->getPriority(), $extensions->getPriority());
    }

    private function views(WidgetRegistry $widgets, ?TabRegistry $tabs = null): ViewCollection
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturn(true);
        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $localizations->method('getLocales')->willReturn(['de', 'en']);
        $provider = $this->createMock(MetadataProviderInterface::class);
        $provider->method('getMetadata')->willReturn(new TypedFormMetadata());
        $metadata = $this->createMock(MetadataProviderRegistry::class);
        $metadata->method('getMetadataProvider')->willReturn($provider);

        $tabs ??= new TabRegistry([new SystemTabProvider(), new TilesTab(), new ExtensionsTabProvider($widgets)], $checker);
        $admin = new DashboardAdmin(new ViewBuilderFactory(), $checker, $localizations, $widgets, $metadata, false, $tabs);
        $collection = new ViewCollection();
        $admin->configureViews($collection);

        return $collection;
    }
}
