<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tab;

/**
 * Optional addition to {@see DashboardTabProviderInterface} for a tab that takes the place of a menu item of another
 * bundle: the page is then reached through the dashboard, and the menu has one entry less. The menu item is removed only
 * when the tab is visible to the user and the option sulu_dashboard.tabs.replace_navigation is on.
 */
interface ReplacesNavigationItemInterface extends DashboardTabProviderInterface
{
    /**
     * Name of the top-level menu item that the tab replaces (the name of its NavigationItem).
     */
    public function getReplacedNavigationItem(): string;

    /**
     * Names of the views of the replaced menu item. They stay as they are (deep links keep working); the menu item of the
     * dashboard is highlighted in them.
     *
     * @return list<string>
     */
    public function getReplacedViews(): array;
}
