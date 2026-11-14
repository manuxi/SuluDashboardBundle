<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\Tab;

use LogicException;
use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardTabPass;
use Manuxi\SuluDashboardBundle\Tab\TabRegistry;
use Manuxi\SuluDashboardBundle\Tests\Unit\Tab\Fixtures\ArrayTab;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class TabRegistryTest extends TestCase
{
    /**
     * @param list<string> $allowed
     */
    private function checker(array $allowed): SecurityCheckerInterface
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(
            static fn (mixed $subject, string $permission): bool => 'view' === $permission && \in_array($subject, $allowed, true),
        );

        return $checker;
    }

    public function testOnlyPermittedTabsAreAvailableHighestPriorityFirst(): void
    {
        $registry = new TabRegistry([
            new ArrayTab('low', priority: 1),
            new ArrayTab('secret', securityContext: 'sulu.secret'),
            new ArrayTab('high', priority: 9, securityContext: 'sulu.ok'),
        ], $this->checker(['sulu.ok']));

        $this->assertSame(['high', 'low'], array_map(static fn ($tab): string => $tab->getKey(), $registry->available()));
    }

    /**
     * @param list<ArrayTab> $tabs
     */
    #[DataProvider('invalidTabs')]
    public function testInvalidTabsAreRejected(array $tabs, string $message): void
    {
        $registry = new TabRegistry($tabs, $this->checker([]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $registry->all();
    }

    /**
     * @return iterable<string, array{list<ArrayTab>, string}>
     */
    public static function invalidTabs(): iterable
    {
        yield 'bad key' => [[new ArrayTab('Bad-Key')], 'invalid'];
        yield 'reserved key' => [[new ArrayTab('overview')], 'invalid'];
        yield 'duplicate' => [[new ArrayTab('same'), new ArrayTab('same')], 'used twice'];
        yield 'no view type' => [[new ArrayTab('a', viewType: ' ')], 'needs a title and a view type'];
    }

    public function testCompilerPassCollectsTheTaggedProviders(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('sulu_dashboard.tab_registry', new Definition(stdClass::class));
        $container->setDefinition('app.tab', (new Definition(ArrayTab::class, ['a']))->addTag('sulu_dashboard.tab'));

        (new DashboardTabPass())->process($container);

        $argument = $container->getDefinition('sulu_dashboard.tab_registry')->getArgument('$sources');
        $this->assertInstanceOf(IteratorArgument::class, $argument);
        $this->assertCount(1, $argument->getValues());
    }

    public function testCompilerPassRejectsATaggedServiceWithoutTheInterface(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('sulu_dashboard.tab_registry', new Definition(stdClass::class));
        $container->setDefinition('app.bad', (new Definition(stdClass::class))->addTag('sulu_dashboard.tab'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"app.bad"');

        (new DashboardTabPass())->process($container);
    }
}
