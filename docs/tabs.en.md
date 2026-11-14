# Tabs: a whole page as a tab of the dashboard

A bundle can bring a whole page (not only a tile) to the dashboard. With at least one tab, the dashboard becomes a tab view like the ones Sulu uses for people and organizations: the tab "Overview" shows the tiles, every provider adds one tab. Without a provider (or if the user may see none), there is no tab bar and the dashboard is a single view.

```php
use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;

final class VisitorsTab implements DashboardTabProviderInterface
{
    public function getKey(): string { return 'visitors'; }                 // part of the path
    public function getTitle(): string { return 'app.visitors'; }           // translation key or text
    public function getViewType(): string { return 'app.visitors_view'; }   // key in Sulu's viewRegistry
    public function getViewOptions(): array { return ['apiUrl' => '/admin/api/visitors']; }
    public function getSecurityContext(): ?string { return 'sulu.app.visitors'; }
    public function getPriority(): int { return 0; }                        // higher first, after "Overview"
}
```

- The view is a React view you register in Sulu's `viewRegistry` as usual (`viewRegistry.add('app.visitors_view', Visitors)`). It reads its options from `router.route.options`.
- With `autoconfigure: true` the service is found automatically (tag `sulu_dashboard.tab`). A bundle that only suggests this bundle loads the service the same way as a tile: with `ContainerBuilder::willBeAvailable()` (see [Your own tile in 5 minutes](widgets.en.md)).
- The user sees the tab only with the view permission of `getSecurityContext()`.
- Keys must be unique; `overview`, `dashboard` and `tabs` are reserved.

## Replacing a menu item

A tab that brings a page which already has a menu item of its own can take its place, so the menu has one entry less. Implement `ReplacesNavigationItemInterface` (extends the tab interface):

```php
public function getReplacedNavigationItem(): string { return 'other_bundle.statistics'; } // name of its NavigationItem
public function getReplacedViews(): array { return ['other_bundle.dashboard']; }          // its views
```

The dashboard runs after all other admin classes and removes the menu item when the tab is visible to the user. The views of the replaced item stay as they are, so deep links keep working, and the menu item "Dashboard" is highlighted in them. Only top-level menu items can be replaced. Switch it off with `sulu_dashboard.tabs.replace_navigation: false`; the menu item then stays.

## Paths

Sulu puts the path of the parent in front of the path of a child. The dashboard handles this for you:

| `start_page` | tab view | Overview | tab `visitors` |
|---|---|---|---|
| `true` (default) | `/` | `/overview` | `/visitors` |
| `false` | `/dashboard` | `/dashboard/overview` | `/dashboard/visitors` |

Opening `/` redirects to "Overview". The search of Sulu stays on `/search`. Existing deep links of a bundle that brings its own page (for example `/log-stats`) stay valid.

## Built-in: visitors from the log statistics

![The tab Visitors](img/dashboard-visitors.de.png)

*The screenshot shows the German admin.*

If `amoifr/sulu-log-stats-bundle` is installed and enabled, and the user has the permission `sulu.settings.log_stats`, the dashboard gets the tab **Visitors** (the statistics page of that bundle, with its options `apiUrl` and `timezone`) and the tile **Visitors (7 days)** in the narrow column: page views of the last seven days, the sum of the daily visitors and the share of error pages (status 4xx and 5xx). The tab replaces the menu item "Statistics" of that bundle (see above). The log statistics are not a requirement of this bundle (`suggest` only); nothing of it is loaded without it.
