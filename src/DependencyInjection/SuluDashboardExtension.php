<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\DependencyInjection;

use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardTabPass;
use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardWidgetPass;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTabProvider;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTilesTabProvider;
use Manuxi\SuluDashboardBundle\Integration\LogStats\LogStatsTileWidget;
use Manuxi\SuluDashboardBundle\Integration\LogStats\VisitorsWidget;
use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;
use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\Extensions\ExtensionsTabProvider;
use Manuxi\SuluDashboardBundle\Widget\System\SystemTileData;
use Manuxi\SuluDashboardBundle\Widget\System\SystemTileWidget;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluDashboardExtension extends Extension
{
    private const LOG_STATS_BUNDLE = 'Amoifr\SuluLogStatsBundle\SuluLogStatsBundle';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('sulu_dashboard.start_page', $config['start_page']);
        $container->setParameter('sulu_dashboard.tabs.replace_navigation', $config['tabs']['replace_navigation']);
        $container->setParameter('sulu_dashboard.extensions.tab', $config['extensions']['tab']);
        $container->setParameter('sulu_dashboard.extensions.keep_on_overview', array_values($config['extensions']['keep_on_overview']));
        $container->setParameter('sulu_dashboard.log_stats.tab', $config['log_stats']['tab']);
        $container->setParameter('sulu_dashboard.layout.balance', $config['layout']['balance']);
        $container->setParameter('sulu_dashboard.system.composer_binary', $config['system']['composer_binary']);
        $container->setParameter('sulu_dashboard.system.storage_dir', $config['system']['storage_dir']);
        $container->setParameter('sulu_dashboard.system.composer_home', $config['system']['composer_home']);
        $container->setParameter('sulu_dashboard.system.max_age', $config['system']['max_age']);
        $container->setParameter('sulu_dashboard.system.cache_limit', $config['system']['cache_limit']);
        $container->setParameter('sulu_dashboard.system.log_limit', $config['system']['log_limit']);
        $container->setParameter('sulu_dashboard.disabled_widgets', array_values($config['disabled_widgets']));

        // every service that implements the interface becomes a tile, without any further configuration
        $container->registerForAutoconfiguration(DashboardWidgetInterface::class)->addTag(DashboardWidgetPass::TAG);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $container->registerForAutoconfiguration(DashboardTabProviderInterface::class)->addTag(DashboardTabPass::TAG);

        $loader->load('services.yaml');

        // the tab "System": one tile service per entry
        foreach (array_keys(SystemTileWidget::TILES) as $name) {
            $container->register(SystemTileWidget::KEY_PREFIX . $name, SystemTileWidget::class)
                ->setArguments([new Reference(SystemTileData::class), $name])
                ->setPublic(false)
                ->addTag(DashboardWidgetPass::TAG);
        }

        // the tab "Extensions" (tiles of other bundles); without it they stay on the overview
        if ($config['extensions']['tab']) {
            $container->register(ExtensionsTabProvider::class, ExtensionsTabProvider::class)
                ->setArguments([new Reference('sulu_dashboard.widget_registry')])
                ->setPublic(false)
                ->addTag(DashboardTabPass::TAG);
        }

        // optional integration: the statistics of amoifr/sulu-log-stats-bundle as the tab "Visitors" and a tile
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];
        if (\in_array(self::LOG_STATS_BUNDLE, \is_array($bundles) ? $bundles : [], true)
            && ContainerBuilder::willBeAvailable('amoifr/sulu-log-stats-bundle', self::LOG_STATS_BUNDLE, ['manuxi/sulu-dashboard-bundle'])) {
            $loader->load('log-stats.yaml');
            if (!$config['log_stats']['overview_tile']) {
                // the figures are in the tab "Visitors"; the class stays for projects that switch the tile on again
                $container->removeDefinition(VisitorsWidget::class);
            }
            $this->loadLogStatsTiles($container, $config['log_stats']['tab']);
        }
    }

    /**
     * Mode "tiles": the tab "Visitors" is built from tiles, one service per tile (a figure, a chart, a top list, the table
     * of days; see LogStatsTileWidget::TILES). Mode "view": the provider stays the one with the view of the other bundle.
     */
    private function loadLogStatsTiles(ContainerBuilder $container, string $mode): void
    {
        if ('tiles' !== $mode) {
            return;
        }

        $container->getDefinition(LogStatsTabProvider::class)->setClass(LogStatsTilesTabProvider::class);

        foreach (array_keys(LogStatsTileWidget::TILES) as $metric) {
            $container->register(LogStatsTileWidget::KEY_PREFIX . $metric, LogStatsTileWidget::class)
                ->setArguments([new Reference(LogStatsTabProvider::class), $metric])
                ->setPublic(false)
                ->addTag(DashboardWidgetPass::TAG);
        }
    }
}
