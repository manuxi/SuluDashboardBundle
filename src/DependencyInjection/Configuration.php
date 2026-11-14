<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sulu_dashboard');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->arrayNode('disabled_widgets')
                    ->info('Keys of tiles that are not shown, for example "sulu_activities". Applies to built-in tiles and tiles of other bundles.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
