<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle;

use Manuxi\SuluDashboardBundle\DependencyInjection\Compiler\DashboardWidgetPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SuluDashboardBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DashboardWidgetPass());
    }
}
