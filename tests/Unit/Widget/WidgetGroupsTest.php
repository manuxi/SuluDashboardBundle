<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget;

use LogicException;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\ArrayWidget;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\GroupedWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * Tiles with a group belong to the tab of that group and are missing in the overview.
 */
class WidgetGroupsTest extends TestCase
{
    private function checker(): SecurityCheckerInterface
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(
            static fn (mixed $subject, string $permission): bool => 'view' === $permission && 'sulu.ok' === $subject,
        );

        return $checker;
    }

    private function registry(): WidgetRegistry
    {
        return new WidgetRegistry([
            new ArrayWidget('pages', priority: 5),
            new GroupedWidget('traffic', 'visitors', priority: 1),
            new GroupedWidget('top_pages', 'visitors', priority: 9),
            new GroupedWidget('events', 'calendar'),
            new GroupedWidget('secret', 'visitors', priority: 20, securityContext: 'sulu.secret'),
        ], $this->checker());
    }

    public function testTheOverviewShowsOnlyTilesWithoutAGroup(): void
    {
        $keys = array_map(static fn ($widget): string => $widget->getKey(), $this->registry()->available());

        $this->assertSame(['pages'], $keys);
    }

    public function testAGroupShowsOnlyItsOwnTilesByPriorityAndOnlyThoseThePermissionAllows(): void
    {
        $keys = array_map(static fn ($widget): string => $widget->getKey(), $this->registry()->available('visitors'));

        $this->assertSame(['top_pages', 'traffic'], $keys);
    }

    public function testAnUnknownGroupIsEmpty(): void
    {
        $this->assertSame([], $this->registry()->available('nothing'));
    }

    public function testATileOfAGroupStaysReachableByItsKey(): void
    {
        $this->assertNotNull($this->registry()->find('traffic'));
        $this->assertNull($this->registry()->find('secret'));
    }

    public function testAnInvalidGroupNameIsRejected(): void
    {
        $registry = new WidgetRegistry([new GroupedWidget('traffic', 'Not Valid')], $this->checker());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('invalid group "Not Valid"');

        $registry->all();
    }
}
