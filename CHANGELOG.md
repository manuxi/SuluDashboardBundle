# Changelog

## Unreleased

### Added

- Display type `chart` (`WidgetTypes::CHART`): a line chart (one or two series) or a bar chart (simple or stacked) drawn as SVG without a library, with rounded axis values, thinned x labels, a tooltip per point or bar, a legend from two series on, a table of the data as a fallback, an accessible name (`role="img"`) and a message for empty data. The series colours are Sulu's own palette. Data: `{kind, stacked?, labels, series: [{label, values, color?}], unit?}`, see `docs/widgets.en.md`.
- The calculations of the chart (axis, thinning, stacking) live in `src/Resources/js/chartMath.js` without React and are tested with `node --test` in CI.

## 1.1.0 - 2026-11-14

### Added

- Balance of the columns: once the tiles have loaded (all have their data and their size has been quiet, at most five seconds), tiles move from the end of the longer column into the shorter one as long as that makes the difference smaller. The order by priority stays in each column; later changes of height move nothing; below 520 px, where Sulu's grid stacks the columns, nothing is balanced; moved tiles fade in unless the user prefers reduced motion. Option `sulu_dashboard.layout.balance` (default true).
- Column `WidgetColumns::AUTO` (tag attribute `column: auto`): no wish, the tile goes into the shorter column.
- `PinnedDashboardWidgetInterface` (tag attribute `pinned: true`): the balance never moves such a tile to the other column. "Open drafts" and "Recent activity" are pinned.
- Tiles get the prop `cache`, an object the dashboard keeps while the page is open, so a tile that moves to the other column (and is mounted again) does not request its data twice. The visitors tile uses it.
- Customize the dashboard: the toolbar button "Customize" shows a handle on every tile (Sulu's block handle, react-sortable-hoc), an eye button to hide a tile and an arrow button to move it to the other column. "Done" ends the mode, "Reset" deletes the own arrangement so the balance decides again. The row of figures can be reordered and hidden but stays a row. Hidden tiles show dimmed while arranging and do not load their data otherwise.
- The arrangement is saved with every change as the user setting `sulu_dashboard.layout`, wins over the balance, and leaves tiles that are new since then to the balance.
- "Done", "Reset" and leaving the page send the arrangement at once instead of after the 2.5 s pause of Sulu's user store.
- The layout functions live in `src/Resources/js/layout.js` without React and can be tested with `node --test tests/js`.

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
