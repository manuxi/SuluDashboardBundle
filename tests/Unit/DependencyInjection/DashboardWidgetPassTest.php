<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tests\Unit\DependencyInjection;

use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardWidgetPass;
use Manuxi\SuluDashboardBundle\Tests\Unit\DependencyInjection\Fixtures\DuckWidget;
use Manuxi\SuluDashboardBundle\Tests\Unit\DependencyInjection\Fixtures\NoDataService;
use Manuxi\SuluDashboardBundle\Tests\Unit\Widget\Fixtures\ArrayWidget;
use Manuxi\SuluDashboardBundle\Widget\TaggedWidget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

class DashboardWidgetPassTest extends TestCase
{
    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('sulu_dashboard.widget_registry', new Definition(stdClass::class));

        return $container;
    }

    /**
     * @return list<string> ids of the services in the registry argument
     */
    private function collected(ContainerBuilder $container): array
    {
        $argument = $container->getDefinition('sulu_dashboard.widget_registry')->getArgument('$sources');
        $this->assertInstanceOf(IteratorArgument::class, $argument);

        return array_map(static fn (Reference $reference): string => (string) $reference, $argument->getValues());
    }

    public function testCollectsServicesThatImplementTheInterface(): void
    {
        $container = $this->container();
        $container->setDefinition('app.widget', (new Definition(ArrayWidget::class, ['one']))->addTag('sulu_dashboard.widget'));
        $container->setDefinition('app.other', new Definition(stdClass::class));

        (new DashboardWidgetPass())->process($container);

        $this->assertSame(['app.widget'], $this->collected($container));
    }

    public function testWrapsServicesWithoutInterfaceInAnAdapter(): void
    {
        $container = $this->container();
        $container->setDefinition('app.duck', (new Definition(DuckWidget::class))->addTag('sulu_dashboard.widget', [
            'key' => 'duck',
            'title' => 'app.duck',
            'type' => 'bars',
            'column' => 'side',
            'priority' => 7,
        ]));

        (new DashboardWidgetPass())->process($container);

        $this->assertSame(['sulu_dashboard.widget.tagged.app.duck'], $this->collected($container));
        $adapter = $container->getDefinition('sulu_dashboard.widget.tagged.app.duck');
        $this->assertSame(TaggedWidget::class, $adapter->getClass());
        $this->assertSame('duck', $adapter->getArgument(1)['key']);
    }

    public function testTaggedAdapterReadsAttributesAndData(): void
    {
        $widget = new TaggedWidget(new DuckWidget(), ['key' => 'duck', 'title' => 'app.duck', 'type' => 'bars', 'column' => 'side', 'priority' => '7', 'security_context' => 'sulu.duck']);

        $this->assertSame('duck', $widget->getKey());
        $this->assertSame('bars', $widget->getType());
        $this->assertSame('side', $widget->getColumn());
        $this->assertSame(7, $widget->getPriority());
        $this->assertSame('sulu.duck', $widget->getSecurityContext());
        $this->assertSame(['items' => [], 'locale' => 'de'], $widget->getData('de'));
    }

    public function testTaggedAdapterFallsBackToDefaults(): void
    {
        $widget = new TaggedWidget(new DuckWidget(), ['key' => 'duck', 'title' => 'app.duck', 'type' => 'bars']);

        $this->assertSame('main', $widget->getColumn());
        $this->assertSame(0, $widget->getPriority());
        $this->assertNull($widget->getSecurityContext());
        $this->assertTrue($widget->isAvailable());
    }

    public function testTaggedAdapterReadsPinnedAndAutoColumn(): void
    {
        $widget = new TaggedWidget(new DuckWidget(), ['key' => 'duck', 'title' => 'app.duck', 'type' => 'bars', 'column' => 'auto', 'pinned' => true]);

        $this->assertSame('auto', $widget->getColumn());
        $this->assertTrue($widget->isPinned());
        $this->assertFalse((new TaggedWidget(new DuckWidget(), ['key' => 'duck', 'title' => 'app.duck', 'type' => 'bars']))->isPinned());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('invalidTags')]
    public function testInvalidTagsFailWhenTheContainerIsCompiled(string $class, array $attributes, string $message): void
    {
        $container = $this->container();
        $container->setDefinition('app.bad', (new Definition($class))->addTag('sulu_dashboard.widget', $attributes));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new DashboardWidgetPass())->process($container);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function invalidTags(): iterable
    {
        $valid = ['key' => 'a', 'title' => 't', 'type' => 'number'];

        yield 'no getData' => [NoDataService::class, $valid, 'getData'];
        yield 'class does not exist' => ['App\\Missing\\Widget', $valid, 'does not exist'];
        yield 'missing key' => [DuckWidget::class, ['title' => 't', 'type' => 'number'], 'attribute "key"'];
        yield 'missing title' => [DuckWidget::class, ['key' => 'a', 'type' => 'number'], 'attribute "title"'];
        yield 'missing type' => [DuckWidget::class, ['key' => 'a', 'title' => 't'], 'attribute "type"'];
        yield 'bad key' => [DuckWidget::class, ['key' => 'A-b'] + $valid, 'invalid'];
        yield 'bad type' => [DuckWidget::class, ['type' => 'pie'] + $valid, 'type "pie"'];
        yield 'bad column' => [DuckWidget::class, ['column' => 'top'] + $valid, 'column "top"'];
        yield 'bad priority' => [DuckWidget::class, ['priority' => 'high'] + $valid, 'priority'];
    }

    public function testDuplicateKeysInTagsFailWhenTheContainerIsCompiled(): void
    {
        $container = $this->container();
        $tag = ['key' => 'same', 'title' => 't', 'type' => 'number'];
        $container->setDefinition('app.one', (new Definition(DuckWidget::class))->addTag('sulu_dashboard.widget', $tag));
        $container->setDefinition('app.two', (new Definition(DuckWidget::class))->addTag('sulu_dashboard.widget', $tag));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"same" is used by the services "app.one" and "app.two"');

        (new DashboardWidgetPass())->process($container);
    }

    public function testDoesNothingWithoutTheRegistry(): void
    {
        $container = new ContainerBuilder();

        (new DashboardWidgetPass())->process($container);

        $this->assertFalse($container->hasDefinition('sulu_dashboard.widget_registry'));
    }
}
