<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Widget;

use LogicException;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\ArrayWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

class WidgetRegistryTest extends TestCase
{
    /**
     * @param list<string> $allowedContexts
     */
    private function checker(array $allowedContexts): SecurityCheckerInterface
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(
            static fn (mixed $subject, string $permission): bool => 'view' === $permission && \in_array($subject, $allowedContexts, true),
        );

        return $checker;
    }

    public function testAvailableIsSortedByPriorityHighestFirst(): void
    {
        $registry = new WidgetRegistry([
            new ArrayWidget('low', priority: 1),
            new ArrayWidget('high', priority: 50),
            new ArrayWidget('middle', priority: 10),
        ], $this->checker([]));

        $keys = array_map(static fn ($widget): string => $widget->getKey(), $registry->available());

        $this->assertSame(['high', 'middle', 'low'], $keys);
    }

    public function testTilesWithoutPermissionAreMissing(): void
    {
        $registry = new WidgetRegistry([
            new ArrayWidget('open'),
            new ArrayWidget('secret', securityContext: 'sulu.secret'),
            new ArrayWidget('shared', securityContext: 'sulu.shared'),
        ], $this->checker(['sulu.shared']));

        $keys = array_map(static fn ($widget): string => $widget->getKey(), $registry->available());

        $this->assertSame(['open', 'shared'], $keys);
        $this->assertNull($registry->find('secret'), 'find() must not reveal a tile the user may not see');
        $this->assertNotNull($registry->find('shared'));
        $this->assertNull($registry->find('unknown'));
    }

    public function testDisabledTilesAreMissing(): void
    {
        $registry = new WidgetRegistry([new ArrayWidget('a'), new ArrayWidget('b')], $this->checker([]), ['a']);

        $this->assertNull($registry->find('a'));
        $this->assertNotNull($registry->find('b'));
    }

    public function testUnavailableConditionalTilesAreMissing(): void
    {
        $registry = new WidgetRegistry([new ArrayWidget('later', available: false), new ArrayWidget('now')], $this->checker([]));

        $this->assertNull($registry->find('later'));
        $this->assertNotNull($registry->find('now'));
    }

    public function testDuplicateKeysAreRejected(): void
    {
        $registry = new WidgetRegistry([new ArrayWidget('same'), new ArrayWidget('same')], $this->checker([]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"same" is used twice');

        $registry->all();
    }

    #[DataProvider('invalidWidgets')]
    public function testInvalidTilesAreRejected(ArrayWidget $widget, string $message): void
    {
        $registry = new WidgetRegistry([$widget], $this->checker([]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $registry->all();
    }

    /**
     * @return iterable<string, array{ArrayWidget, string}>
     */
    public static function invalidWidgets(): iterable
    {
        yield 'upper case key' => [new ArrayWidget('Bad'), 'invalid'];
        yield 'dash in key' => [new ArrayWidget('bad-key'), 'invalid'];
        yield 'unknown type' => [new ArrayWidget('a', type: 'pie'), 'unknown type "pie"'];
        yield 'unknown column' => [new ArrayWidget('a', column: 'footer'), 'unknown column "footer"'];
        yield 'empty title' => [new ArrayWidget('a', title: ' '), 'empty title'];
    }
}
