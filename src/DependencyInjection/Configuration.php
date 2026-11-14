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
                ->booleanNode('start_page')
                    ->defaultTrue()
                    ->info('true: the dashboard is the start page of the admin ("/") and the search of Sulu moves to "/search". false: the dashboard is on "/dashboard", the search stays on "/".')
                ->end()
                ->arrayNode('tabs')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('replace_navigation')
                            ->defaultTrue()
                            ->info('true: a tab that replaces a menu item of another bundle (for example the statistics of the log statistics) removes that item from the menu. false: the item stays.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('disabled_widgets')
                    ->info('Keys of tiles that are not shown, for example "sulu_activities". Applies to built-in tiles and tiles of other bundles.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
