<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\DependencyInjection\Compiler;

use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects all services tagged "sulu_dashboard.tab" (set automatically for every service that implements
 * DashboardTabProviderInterface).
 */
final class DashboardTabPass implements CompilerPassInterface
{
    public const TAG = 'sulu_dashboard.tab';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('sulu_dashboard.tab_registry')) {
            return;
        }

        $references = [];
        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            if (!\is_string($class) || !is_subclass_of($class, DashboardTabProviderInterface::class)) {
                throw new InvalidArgumentException(\sprintf('The service "%s" is tagged "%s", so it must implement %s.', $id, self::TAG, DashboardTabProviderInterface::class));
            }

            $references[] = new Reference($id);
        }

        $container->getDefinition('sulu_dashboard.tab_registry')->setArgument('$sources', new IteratorArgument($references));
    }
}
