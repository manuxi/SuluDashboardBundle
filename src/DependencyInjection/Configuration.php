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
                ->arrayNode('extensions')
                    ->addDefaultsIfNotSet()
                    ->info('Tiles of other bundles and of the project.')
                    ->children()
                        ->booleanNode('tab')
                            ->defaultTrue()
                            ->info('true: tiles without a group of their own whose class is not part of this bundle appear in the tab "Extensions" (only while the user may see one), not on the overview. false: they stay on the overview, as before version 1.5.')
                        ->end()
                        ->arrayNode('keep_on_overview')
                            ->info('Keys of tiles from other bundles that stay on the overview although the tab "Extensions" is on, for example "testimonials_ratings".')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('log_stats')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('overview_tile')
                            ->defaultFalse()
                            ->info('true: the small tile "Visitors (7 days)" (sulu_visitors) is shown on the overview. false (default): the visitor figures live in the tab "Visitors" only.')
                        ->end()
                        ->enumNode('tab')
                            ->values(['tiles', 'view'])
                            ->defaultValue('tiles')
                            ->info('Only with amoifr/sulu-log-stats-bundle. tiles: the tab "Visitors" is built from tiles (figures, charts, top lists, table of days) that the user can arrange and hide; the original page of that bundle is removed and its path opens the tab. view: the tab shows the original page of that bundle (technical fallback, no arranging).')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('layout')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('balance')
                            ->defaultTrue()
                            ->info('true: after loading, the dashboard moves tiles from the end of the longer column into the shorter one, so no big gap remains. false: nothing is measured, every tile stays in the column it asks for and tiles with "auto" are spread evenly by number. An arrangement saved by a user always wins.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('system')
                    ->addDefaultsIfNotSet()
                    ->info('The tile "System" (permission sulu.settings.dashboard_system).')
                    ->children()
                        ->scalarNode('composer_binary')
                            ->defaultValue('composer')
                            ->info('Composer for the security check of sulu:dashboard:system-check: a command in the PATH ("composer"), or the path to a composer.phar.')
                        ->end()
                        ->integerNode('max_age')
                            ->defaultValue(48)
                            ->min(1)
                            ->info('Hours after which the tile reports that the last run of sulu:dashboard:system-check is too old (default 48, for a daily cron job).')
                        ->end()
                        ->integerNode('cache_limit')
                            ->defaultValue(1024)
                            ->min(0)
                            ->info('Size of var/cache in MB from which the tile "Storage" gives a hint (default 1024, 0 turns the hint off).')
                        ->end()
                        ->integerNode('log_limit')
                            ->defaultValue(512)
                            ->min(0)
                            ->info('Size of var/log in MB from which the tile "Storage" gives a hint (default 512, 0 turns the hint off).')
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
