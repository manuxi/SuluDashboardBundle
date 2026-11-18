<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

/**
 * Optional addition to {@see DashboardWidgetInterface} for a tile that belongs to a group. The tab "Overview" shows only
 * tiles without a group; a tile with a group appears in the tab whose provider names the same group
 * ({@see \Manuxi\SuluDashboardBundle\Tab\TilesTabProviderInterface}) and nowhere else. The endpoint of the tile does not
 * change.
 */
interface GroupedDashboardWidgetInterface extends DashboardWidgetInterface
{
    /**
     * Name of the group: lower case letters, digits and underscores, starting with a letter ("visitors"). It becomes part
     * of the key under which the arrangement of the tab is saved.
     */
    public function getGroup(): string;
}
