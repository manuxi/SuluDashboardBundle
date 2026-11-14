<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Widget;

/**
 * Optional addition to {@see DashboardWidgetInterface} for a tile that must stay in the column it asks for. The
 * automatic balance of the columns (sulu_dashboard.layout.balance) never moves it to the other column; a user can still
 * move it in the customize mode. Meant for the core of a dashboard, for example the list of open drafts, that people
 * look for in the same place every day.
 */
interface PinnedDashboardWidgetInterface extends DashboardWidgetInterface
{
    public function isPinned(): bool;
}
