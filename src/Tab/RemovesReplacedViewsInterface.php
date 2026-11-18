<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Tab;

/**
 * Optional addition to {@see ReplacesNavigationItemInterface} for a tab that shows the same content as the replaced
 * views, built from tiles. The dashboard then also removes those views from Sulu's view collection and puts a view on
 * their path that redirects to the tab, so an old link or bookmark opens the tab. Like the menu item, the views are
 * removed only while the tab is visible to the user and sulu_dashboard.tabs.replace_navigation is on.
 */
interface RemovesReplacedViewsInterface extends ReplacesNavigationItemInterface
{
}
