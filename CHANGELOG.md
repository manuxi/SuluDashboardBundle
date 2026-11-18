# Changelog

## Unreleased

### Added

- Built-in tile "System" (`sulu_system`, list) for administrators: versions of PHP, Symfony and Sulu, environment and debug mode with a red warning when debug is on in `prod`, known vulnerabilities from `composer audit`, open Doctrine migrations (only with `doctrine/doctrine-migrations-bundle`), failed Messenger messages (only with a `failure_transport`) and the space used by `public/uploads` and `var`. Nothing secret is shown (no connection strings, keys or absolute paths). See [docs/system.en.md](docs/system.en.md).
- Own permission `sulu.settings.dashboard_system` (view) under Settings; the tile is only on the dashboard of users who have it besides `sulu.settings.dashboard`.
- Command `sulu:dashboard:system-check` for a cron job: runs `composer audit --locked` (no shell, command as a list) and measures the folders, and saves the result in the cache (`cache.app` of the admin context). Nothing slow runs in a web request. The tile shows the age of the check, says so when the command never ran and warns when the last run is older than `sulu_dashboard.system.max_age` hours (default 48). Exit code 1 when the check could not run, not when vulnerabilities were found.
- Options `sulu_dashboard.system.composer_binary` (default `composer`, also a path to a `composer.phar`) and `sulu_dashboard.system.max_age`.
- List tiles: rows may carry `severity` (`error`, `warning`, with Sulu's warning icon) and the data a `limit` (rows before "Show all", default 10). Texts may be `['text' => '8.4.27']` to be shown as they are, never taken for a translation key.
- New requirement `symfony/process` (always present in a Sulu project); `doctrine/doctrine-migrations-bundle` and `symfony/messenger` only under `suggest`.

## 1.2.0 - 2026-11-18

### Added

- Tile groups: `GroupedDashboardWidgetInterface` (`getGroup()`). A tile with a group appears only in the tab of that group, the overview shows only tiles without a group. `WidgetRegistry::available()` takes the group as an optional argument.
- Tabs of tiles: `TilesTabProviderInterface` (extends the tab interface) builds a tab from the tiles of a group instead of a view of another bundle. The tab is the dashboard view with the tiles of its group and the options `settingKey`, `period` and `showLocale`.
- Every tab of tiles saves its arrangement under its own user setting (`sulu_dashboard.layout.<group>`); the overview keeps `sulu_dashboard.layout`. The view reads the key from the option `settingKey`.
- Period for tabs of tiles (`getPeriods()`, `getDefaultPeriod()`): a select in the toolbar (for example 7, 30, 90, 365 days) and "Custom period ..." with a bar of two date pickers (from, to) and "Apply" above the tiles. A missing day, a start after the end and more than 366 days show an error in the bar. The choice is saved per tab as a user setting at once. Tiles get the prop `period`: `{days}` or `{from, to}` (`YYYY-MM-DD`). The overview has no period.
- `showLocale(): false` hides the language select of a tab of tiles.
- The tab "Visitors" of the log statistics is made of tiles (group `visitors`, `sulu_dashboard.log_stats.tab: tiles`, the default): eight figures in the row, five charts (page views, visitors, status stacked, time of day, response time), three top lists and the table of days, with the period (7, 30, 90, 365 days or a custom one) and the "Customize" mode. `tab: view` keeps the page of the other bundle (technical fallback).
- In the mode `tiles` the dashboard also removes the view `amoifr_log_stats.dashboard` of the log statistics (`RemovesReplacedViewsInterface`); its path (`#/log-stats`) redirects to the tab through the new view `sulu_dashboard.redirect`. Only together with the replaced menu item.
- All tiles of the tab share one request per period (`logStats.js`, `logStatsStore.js`); the tile "Visitors (7 days)" uses it too. A tile that lacks fields it expects says which ones, instead of showing zeros. The expected answer is kept in `tests/fixtures/log-stats-response.json` and tested with `node --test tests/js/*.test.mjs`. `suggest` names the version `~0.1`.
- A text in tile data that is only digits and dots ("1.204") is no longer taken for a translation key.
- The period functions live in `src/Resources/js/period.js` without React and can be tested with `node --test tests/js/*.test.mjs`.
- Display type `chart` (`WidgetTypes::CHART`): a line chart (one or two series) or a bar chart (simple or stacked) drawn as SVG without a library, with rounded axis values, thinned x labels, a tooltip per point or bar, a legend from two series on, a table of the data as a fallback, an accessible name (`role="img"`) and a message for empty data. The series colours are Sulu's own palette. Data: `{kind, stacked?, labels, series: [{label, values, color?}], unit?}`, see `docs/widgets.en.md`.
- The calculations of the chart (axis, thinning, stacking) live in `src/Resources/js/chartMath.js` without React and are tested with `node --test tests/js/*.test.mjs` in CI.
- Chart colours: Sulu's palette as it is (Sass colour functions such as `mix()` are not evaluated by the admin build). The axis labels use `$doveGray` (5.1:1 instead of 3.6:1). Stacked bars get a white 1px gap, the status chart uses green, blue, yellow and red (2xx to 5xx) and the response time blue and yellow, so neighbours differ in lightness and hue. Legend, tooltips and the table of the data stay as a second way to read the values. The Sulu 3.0 admin has no dark mode, so there is nothing to follow.
- The table of days of the tab "Visitors" is pinned to the wide column. The response time of the log statistics may be empty (`null`, which Sulu's Requester turns into `undefined`); that is no missing field.
- A list tile without a meta column (for example an empty list) no longer breaks Sulu's table.
- Screenshots: `docs/img/dashboard-visitors.de.png` (updated) and `docs/img/dashboard-period.de.png` (new).

## 1.1.0 - 2026-11-14

### Added

- Balance of the columns: once the tiles have loaded (all have their data and their size has been quiet, at most five seconds), tiles move from the end of the longer column into the shorter one as long as that makes the difference smaller. The order by priority stays in each column; later changes of height move nothing; below 520 px, where Sulu's grid stacks the columns, nothing is balanced; moved tiles fade in unless the user prefers reduced motion. Option `sulu_dashboard.layout.balance` (default true).
- Column `WidgetColumns::AUTO` (tag attribute `column: auto`): no wish, the tile goes into the shorter column.
- `PinnedDashboardWidgetInterface` (tag attribute `pinned: true`): the balance never moves such a tile to the other column. "Open drafts" and "Recent activity" are pinned.
- Tiles get the prop `cache`, an object the dashboard keeps while the page is open, so a tile that moves to the other column (and is mounted again) does not request its data twice. The visitors tile uses it.
- Customize the dashboard: the toolbar button "Customize" shows a handle on every tile (Sulu's block handle, react-sortable-hoc), an eye button to hide a tile and an arrow button to move it to the other column. "Done" ends the mode, "Reset" deletes the own arrangement so the balance decides again. The row of figures can be reordered and hidden but stays a row. Hidden tiles show dimmed while arranging and do not load their data otherwise.
- The arrangement is saved with every change as the user setting `sulu_dashboard.layout`, wins over the balance, and leaves tiles that are new since then to the balance.
- "Done", "Reset" and leaving the page send the arrangement at once instead of after the 2.5 s pause of Sulu's user store.
- The layout functions live in `src/Resources/js/layout.js` without React and can be tested with `node --test tests/js/*.test.mjs`.

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
