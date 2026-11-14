# Changelog

## 1.0.0 - 2026-11-14

First release of SuluDashboardBundle: a start page for the Sulu 3 admin and a small interface for other bundles and projects to add tiles and tabs.

### Added

- Start page of the admin (view `sulu_dashboard.dashboard`, menu item "Dashboard") built from the components of Sulu: toolbar with the language select, a row of figures and two columns. The menu item is highlighted in every view of the dashboard.
- Option `sulu_dashboard.start_page` (default true): the dashboard owns `/` and the search of Sulu moves to `/search` (name, menu item and links unchanged); with false the dashboard is on `/dashboard`. The admin class runs last (priority -1024) so the search view exists when it is moved.
- Interface `DashboardWidgetInterface` (plus the optional `ConditionalDashboardWidgetInterface`), collected by a compiler pass. Autoconfiguration tags every implementation with `sulu_dashboard.widget`; services without the interface can be tagged by hand with `key`, `title` and `type` and are checked while the container is compiled. Display types `number`, `progress` (rings or bars, optional footer list), `list` (second line, footer) and `bars`, and `custom` for own React components (JavaScript `widgetRegistry`). Every text may be plain text, an admin translation key or a key with parameters.
- Endpoint `GET /admin/api/dashboard/widgets/{key}?locale=` per tile; an error stays inside its tile, is logged and never shows the message of the exception.
- Built-in tiles: pages, articles, snippets, media, open drafts, search engine details (rings and "Add first"), translations (with "What is missing?") and recent activity. Content figures respect the webspace and article type permissions.
- Tabs: `DashboardTabProviderInterface` (tag `sulu_dashboard.tab`) adds a whole page as a tab. With at least one visible tab the dashboard becomes a tab view ("Overview" plus one tab per provider), otherwise it stays a plain view without a tab bar. Paths work with both values of `start_page`.
- A tab can take the place of a menu item of another bundle (`ReplacesNavigationItemInterface`): the item is removed from the menu, its views and deep links stay, and the menu item of the dashboard is highlighted in them. Option `sulu_dashboard.tabs.replace_navigation` (default true).
- Optional integration with `amoifr/sulu-log-stats-bundle` (only `suggest`): the tab "Visitors" replaces its menu item "Statistics", and the tile "Visitors (7 days)" shows page views, visitors and the share of error pages. Nothing is loaded without that bundle.
- Security context `sulu.settings.dashboard` (view), under Settings. Without it Sulu behaves as before.
- Command `sulu:dashboard:dump --user=<name>` prints the tiles of a user with their data as JSON; the permissions of that user apply.
- Option `sulu_dashboard.disabled_widgets`.
- Translations in German, English and Dutch; documentation in German and English with screenshots.
