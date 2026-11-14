<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget\Builtin;

use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;

/**
 * Defaults of the built-in tiles: no permission of their own (the data respects the permissions of the content).
 */
abstract class AbstractBuiltinWidget implements DashboardWidgetInterface
{
    public function getSecurityContext(): ?string
    {
        return null;
    }
}
