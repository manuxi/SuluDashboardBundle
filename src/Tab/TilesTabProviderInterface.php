<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tab;

/**
 * Optional addition to {@see DashboardTabProviderInterface} for a tab that is built from tiles instead of a view of
 * another bundle. The tab shows the tiles of its group ({@see \Manuxi\SuluDashboardBundle\Widget\GroupedDashboardWidgetInterface}),
 * with the same "Customize" mode as the overview; the arrangement is saved for each group on its own.
 *
 * The dashboard sets the view type and the options widgets, settingKey, period and showLocale itself; the view type and
 * the options of the provider are only added to them (return the view type of the dashboard, `DashboardAdmin::VIEW`).
 */
interface TilesTabProviderInterface extends DashboardTabProviderInterface
{
    /**
     * Group of the tiles of this tab.
     */
    public function getGroup(): string;

    /**
     * Periods in days that the user can choose in the toolbar (for example [7, 30, 90, 365]), next to "Custom period".
     * An empty list means the tiles have no period and the toolbar has no field for it.
     *
     * @return list<int>
     */
    public function getPeriods(): array;

    /**
     * The period before the user chose one, in days. Used only if it is one of {@see getPeriods()}, otherwise the first
     * of them.
     */
    public function getDefaultPeriod(): int;

    /**
     * Whether the toolbar offers the language select. Off for tiles whose data does not depend on the language.
     */
    public function showLocale(): bool;
}
