<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tab;

/**
 * Optional addition to {@see DashboardTabProviderInterface} for a tab that only exists while there is something to show
 * (the tab "Extensions" needs at least one tile the user may see). Evaluated for the current user whenever the tabs are
 * asked for, so keep it cheap.
 */
interface ConditionalDashboardTabInterface
{
    public function isAvailable(): bool;
}
