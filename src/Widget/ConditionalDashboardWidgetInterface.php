<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

/**
 * Optional addition to {@see DashboardWidgetInterface} for a tile that can only be shown under certain conditions,
 * for example when an API key is configured.
 */
interface ConditionalDashboardWidgetInterface extends DashboardWidgetInterface
{
    public function isAvailable(): bool;
}
