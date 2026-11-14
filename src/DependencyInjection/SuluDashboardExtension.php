<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\DependencyInjection;

use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardWidgetPass;
use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluDashboardExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('sulu_dashboard.start_page', $config['start_page']);
        $container->setParameter('sulu_dashboard.disabled_widgets', array_values($config['disabled_widgets']));

        // every service that implements the interface becomes a tile, without any further configuration
        $container->registerForAutoconfiguration(DashboardWidgetInterface::class)->addTag(DashboardWidgetPass::TAG);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
