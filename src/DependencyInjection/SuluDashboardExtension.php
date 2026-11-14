<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\DependencyInjection;

use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardTabPass;
use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardWidgetPass;
use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;
use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluDashboardExtension extends Extension
{
    private const LOG_STATS_BUNDLE = 'Amoifr\SuluLogStatsBundle\SuluLogStatsBundle';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('sulu_dashboard.start_page', $config['start_page']);
        $container->setParameter('sulu_dashboard.disabled_widgets', array_values($config['disabled_widgets']));

        // every service that implements the interface becomes a tile, without any further configuration
        $container->registerForAutoconfiguration(DashboardWidgetInterface::class)->addTag(DashboardWidgetPass::TAG);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $container->registerForAutoconfiguration(DashboardTabProviderInterface::class)->addTag(DashboardTabPass::TAG);

        $loader->load('services.yaml');

        // optional integration: the statistics of amoifr/sulu-log-stats-bundle as the tab "Visitors" and a tile
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];
        if (\in_array(self::LOG_STATS_BUNDLE, \is_array($bundles) ? $bundles : [], true)
            && ContainerBuilder::willBeAvailable('amoifr/sulu-log-stats-bundle', self::LOG_STATS_BUNDLE, ['manuxi/sulu-dashboard-bundle'])) {
            $loader->load('log-stats.yaml');
        }
    }
}
