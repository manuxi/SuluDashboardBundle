<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Admin;

use LogicException;
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Tab\TabRegistry;
use Manuxi\SuluDashboardBundle\Tests\Unit\Tab\Fixtures\TilesTab;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\ArrayWidget;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\GroupedWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * A tab built from the tiles of a group: view type, tiles, setting key, period and language select.
 */
class DashboardTileTabsTest extends TestCase
{
    /**
     * @param list<TilesTab> $tabs
     */
    private function views(array $tabs): ViewCollection
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturn(true);
        $localizations = $this->createMock(LocalizationManagerInterface::class);
        $localizations->method('getLocales')->willReturn(['de', 'en']);
        $provider = $this->createMock(MetadataProviderInterface::class);
        $provider->method('getMetadata')->willReturn(new TypedFormMetadata());
        $metadata = $this->createMock(MetadataProviderRegistry::class);
        $metadata->method('getMetadataProvider')->willReturn($provider);

        $widgets = new WidgetRegistry([
            new ArrayWidget('pages'),
            new GroupedWidget('traffic', 'visitors'),
            new GroupedWidget('top_pages', 'visitors', priority: 5),
            new GroupedWidget('events', 'calendar'),
        ], $checker);

        $admin = new DashboardAdmin(new ViewBuilderFactory(), $checker, $localizations, $widgets, $metadata, false, new TabRegistry($tabs, $checker));
        $collection = new ViewCollection();
        $admin->configureViews($collection);

        return $collection;
    }

    /**
     * @return list<string>
     */
    private function keysOf(mixed $widgets): array
    {
        return array_map(static fn (array $widget): string => $widget['key'], $widgets);
    }

    public function testTheOverviewHasOnlyTilesWithoutAGroup(): void
    {
        $overview = $this->views([new TilesTab()])->get(DashboardAdmin::VIEW)->getView();

        $this->assertSame(['pages'], $this->keysOf($overview->getOption('widgets')));
        $this->assertNull($overview->getOption('settingKey'), 'the overview keeps the default key of the view');
        $this->assertNull($overview->getOption('period'), 'the overview has no period');
    }

    public function testATabOfTilesIsTheDashboardViewWithItsGroupAndItsOwnSettingKey(): void
    {
        $tab = $this->views([new TilesTab()])->get('sulu_dashboard.tab_visitors')->getView();

        $this->assertSame(DashboardAdmin::VIEW, $tab->getType());
        $this->assertSame(['top_pages', 'traffic'], $this->keysOf($tab->getOption('widgets')));
        $this->assertSame('sulu_dashboard.layout.visitors', $tab->getOption('settingKey'));
        $this->assertSame(['de', 'en'], $tab->getOption('locales'));
        $this->assertFalse($tab->getOption('showLocale'));
    }

    public function testTwoTabsAreArrangedOnTheirOwn(): void
    {
        $views = $this->views([new TilesTab(), new TilesTab('calendar', 'calendar', showLocale: true)]);

        $this->assertSame('sulu_dashboard.layout.visitors', $views->get('sulu_dashboard.tab_visitors')->getView()->getOption('settingKey'));
        $this->assertSame('sulu_dashboard.layout.calendar', $views->get('sulu_dashboard.tab_calendar')->getView()->getOption('settingKey'));
        $this->assertSame(['events'], $this->keysOf($views->get('sulu_dashboard.tab_calendar')->getView()->getOption('widgets')));
        $this->assertTrue($views->get('sulu_dashboard.tab_calendar')->getView()->getOption('showLocale'));
    }

    public function testThePeriodsAndTheDefaultAreHandedToTheView(): void
    {
        $tab = $this->views([new TilesTab()])->get('sulu_dashboard.tab_visitors')->getView();

        $this->assertSame(['days' => [7, 30, 90, 365], 'default' => 30, 'maxDays' => 366], $tab->getOption('period'));
    }

    public function testADefaultThatIsNotInTheListFallsBackToTheFirstPeriod(): void
    {
        $tab = $this->views([new TilesTab(periods: [14, 60], defaultPeriod: 30)])->get('sulu_dashboard.tab_visitors')->getView();

        $this->assertSame(['days' => [14, 60], 'default' => 14, 'maxDays' => 366], $tab->getOption('period'));
    }

    public function testWithoutPeriodsThereIsNoPeriodOption(): void
    {
        $tab = $this->views([new TilesTab(periods: [])])->get('sulu_dashboard.tab_visitors')->getView();

        $this->assertNull($tab->getOption('period'));
    }

    public function testTheOptionsOfTheProviderWinOverTheOnesOfTheDashboard(): void
    {
        $tab = $this->views([new TilesTab(options: ['apiUrl' => '/api/x', 'showLocale' => true])])->get('sulu_dashboard.tab_visitors')->getView();

        $this->assertSame('/api/x', $tab->getOption('apiUrl'));
        $this->assertTrue($tab->getOption('showLocale'));
    }

    /**
     * @return iterable<string, array{TilesTab, string}>
     */
    public static function invalidTabs(): iterable
    {
        yield 'bad group' => [new TilesTab(group: 'Not Valid'), 'invalid group'];
        yield 'zero days' => [new TilesTab(periods: [7, 0]), 'invalid period'];
        yield 'more than a year' => [new TilesTab(periods: [367]), 'invalid period'];
    }

    #[DataProvider('invalidTabs')]
    public function testInvalidTilesTabsAreRejected(TilesTab $tab, string $message): void
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        (new TabRegistry([$tab], $checker))->all();
    }
}
