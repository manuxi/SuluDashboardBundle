<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\DependencyInjection\Compiler;

use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\TaggedWidget;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects all services tagged "sulu_dashboard.widget" (set automatically for every service that implements
 * DashboardWidgetInterface) and checks them while the container is compiled.
 *
 * Besides the interface, a service without any dependency on this bundle can be tagged by hand: it needs a public method
 * getData(string $locale): array, and key, title and type as tag attributes (or as methods).
 */
final class DashboardWidgetPass implements CompilerPassInterface
{
    public const TAG = 'sulu_dashboard.widget';

    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('sulu_dashboard.widget_registry')) {
            return;
        }

        $references = [];
        $keys = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);
            if (!\is_string($class) || !class_exists($class)) {
                throw new InvalidArgumentException(\sprintf('The service "%s" is tagged "%s", but its class "%s" does not exist.', $id, self::TAG, \is_string($class) ? $class : $id));
            }

            if (is_subclass_of($class, DashboardWidgetInterface::class)) {
                $references[] = new Reference($id);

                continue;
            }

            if (!method_exists($class, 'getData')) {
                throw new InvalidArgumentException(\sprintf('The service "%s" is tagged "%s", so %s must implement %s or at least have a public method getData(string $locale): array.', $id, self::TAG, $class, DashboardWidgetInterface::class));
            }

            $attributes = $this->mergeAttributes($tags);
            $this->validateAttributes($id, $class, $attributes);

            if (isset($attributes['key'])) {
                if (isset($keys[$attributes['key']])) {
                    throw new InvalidArgumentException(\sprintf('The dashboard widget key "%s" is used by the services "%s" and "%s".', $attributes['key'], $keys[$attributes['key']], $id));
                }
                $keys[$attributes['key']] = $id;
            }

            $adapterId = 'sulu_dashboard.widget.tagged.' . $id;
            $container->setDefinition($adapterId, new Definition(TaggedWidget::class, [new Reference($id), $attributes]));
            $references[] = new Reference($adapterId);
        }

        $container->getDefinition('sulu_dashboard.widget_registry')->setArgument('$sources', new IteratorArgument($references));
    }

    /**
     * @param list<array<string, mixed>> $tags
     *
     * @return array<string, mixed>
     */
    private function mergeAttributes(array $tags): array
    {
        $attributes = [];
        foreach ($tags as $tag) {
            $attributes = [...$attributes, ...$tag];
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function validateAttributes(string $id, string $class, array $attributes): void
    {
        foreach (['key', 'title', 'type'] as $required) {
            if (!isset($attributes[$required]) && !method_exists($class, 'get' . ucfirst($required))) {
                throw new InvalidArgumentException(\sprintf('The service "%s" (%s) is tagged "%s" without the attribute "%s" and has no method get%s().', $id, $class, self::TAG, $required, ucfirst($required)));
            }
        }

        if (isset($attributes['key']) && (!\is_string($attributes['key']) || 1 !== preg_match(self::KEY_PATTERN, $attributes['key']))) {
            throw new InvalidArgumentException(\sprintf('The key "%s" of the dashboard widget "%s" is invalid: use lower case letters, digits and underscores, starting with a letter.', $this->printable($attributes['key']), $id));
        }

        if (isset($attributes['type']) && !\in_array($attributes['type'], WidgetTypes::all(), true)) {
            throw new InvalidArgumentException(\sprintf('The type "%s" of the dashboard widget "%s" is unknown. Allowed: %s.', $this->printable($attributes['type']), $id, implode(', ', WidgetTypes::all())));
        }

        if (isset($attributes['column']) && !\in_array($attributes['column'], WidgetColumns::all(), true)) {
            throw new InvalidArgumentException(\sprintf('The column "%s" of the dashboard widget "%s" is unknown. Allowed: %s.', $this->printable($attributes['column']), $id, implode(', ', WidgetColumns::all())));
        }

        if (isset($attributes['priority']) && !is_numeric($attributes['priority'])) {
            throw new InvalidArgumentException(\sprintf('The priority of the dashboard widget "%s" must be a number.', $id));
        }
    }

    private function printable(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : \gettype($value);
    }
}
