# Changelog

## Unreleased

### Added

- The tab **Extensions** (de "Erweiterungen", nl "Extensies") for the tiles that other bundles and the project bring: a tab of tiles (group `extensions`, own setting key `sulu_dashboard.layout.extensions`, "Customize" mode, language select, no period). It stands after "Overview" and before "Visitors" and "System" (priority 10) and exists only while the user may see at least one such tile ("no extensions, no tab"). See [docs/tabs.en.md](docs/tabs.en.md) and "Where does my tile appear?" in [docs/widgets.en.md](docs/widgets.en.md).
- Rule for the assignment, decided by the bundle itself (`WidgetRegistry::groupFor()`), the other bundles stay unchanged: a tile with its own group keeps it; a tile without a group whose class is not in the namespace `Manuxi\SuluDashboardBundle\` goes to "Extensions" (for a tagged service the class of the service counts; tiles of the project count like those of other bundles). The built-in tiles stay on the overview.
- Options `sulu_dashboard.extensions.tab` (default `true`; `false` restores the old behaviour: everything on the overview) and `sulu_dashboard.extensions.keep_on_overview` (keys of tiles of other bundles that stay on the overview).
- `ConditionalDashboardTabInterface` (`isAvailable(): bool`): a tab provider can hide its tab. `sulu:dashboard:dump --group=extensions` prints the tiles of the new tab.

### Changed

- The tile **Visitors (7 days)** (`sulu_visitors`) is no longer on the overview, because the tab "Visitors" shows the same figures in more detail. New option `sulu_dashboard.log_stats.overview_tile` (default `false`; `true` registers the tile as before). Class and key stay; a saved arrangement with the key causes no error.
- Tiles of other bundles that were on the overview before (for example ratings, glossary, DeepL quota) move to the tab "Extensions". Projects that want the old place set `sulu_dashboard.extensions.tab: false` or list single keys under `keep_on_overview`.
- `WidgetRegistry` takes two optional constructor arguments (`$extensions`, `$keepOnOverview`) and has the new methods `groupFor()` and `isForeign()`; `groupOf()` is unchanged.

## 1.4.0 - 2026-11-19

### Added

- The tile **Storage** of the tab "System" is a tile of its own now (React component `widgets/SystemStorage.js`, wide column): the total size of the measured folders, one bar per area with size and share (`public/uploads`, `var/cache`, `var/log`, `var/storage` and every other folder in `var`, the six biggest, the rest in one row), the change since the measurement before (per folder and in total, from 1 MB on), the number and size of the media of Sulu and, always, the age of the measurement ("Measured 3 hours ago by the command ..., not live."). Free disk space is left out on purpose: on shared hosting it is the space of the whole server.
- Hints for a big `var/cache` and `var/log` (yellow bar and a short message), also as a warning row in the small tile on the overview. Options `sulu_dashboard.system.cache_limit` (default 1024 MB) and `sulu_dashboard.system.log_limit` (default 512 MB), `0` turns a hint off.
- `sulu:dashboard:system-check` now reports `var` by its subfolders, reads the number and size of the media from Sulu's media tables (`MediaStatistics`, only in the command) and keeps the measurement before inside the result (`SystemCheck::$previous`). Results of earlier versions are still read; the change appears after the second run.
- Texts in de, en and nl; tests for the report (`StorageReportTest`), the measurer, the checker, the media statistics and the pure logic of the component (`tests/js/storage.test.mjs`).

### Changed

- The tiles "Database migrations" and "Failed messages" (one sentence each) are pinned to the narrow column, so the balance does not move them into the wide one. The tile "Storage" moves to the wide column and may be moved by the balance.
- `StorageMeasurer` takes a third argument `$splitPaths` (default `['var']`) and then reports the subfolders of `var` instead of one entry `var`. `SystemChecker` and `SystemTileData` take an optional last argument (`MediaStatistics`, `StorageReport`).

## 1.3.0 - 2026-11-18

### Added

- The tab "System" for administrators, a tab of tiles like "Visitors" (group `system`, own setting key `sulu_dashboard.layout.system`, "Customize" mode, no period, no language select). Tab and every tile need the permission `sulu.settings.dashboard_system`; without it there is no tab. Tiles: **Security vulnerabilities** (wide column, pinned: Sulu table with package, installed version, CVE or advisory ID, title as a link to the source in a new tab, severity, date reported, the worst first, ten rows and "Show all"; the message "No known security vulnerabilities." without findings; below it the packages that are no longer maintained), **Last check** (age, warning for never or too old, always with the command as text), **Versions**, **Environment** (red warning for debug mode in `prod`), **Storage**, **Database migrations** and **Failed messages** (the last two only where there is something to read). See [docs/system.en.md](docs/system.en.md).
- Own permission `sulu.settings.dashboard_system` (view) under Settings; it is needed besides `sulu.settings.dashboard` for the tab and for the tile on the overview.
- Command `sulu:dashboard:system-check` for a cron job: runs `composer audit --locked` (no shell, command as a list) and measures the folders, and saves the result in the cache (`cache.app` of the admin context). Nothing slow runs in a web request. It now saves per advisory the package, the installed version, the advisory ID, CVE, title, link (only `http`/`https`), severity (`critical`, `high`, `medium`, `low`, if Composer delivers it) and the date reported, up to 200 advisories, and the abandoned packages with their replacement. A result of an older version is still read (the new fields are empty until the command runs again). Exit code 1 when the check could not run, not when vulnerabilities were found.
- Options `sulu_dashboard.system.composer_binary` (default `composer`, also a path to a `composer.phar`) and `sulu_dashboard.system.max_age` (hours until the last check counts as too old, default 48).
- List tiles: rows may carry `severity` `error`, `warning` or `ok` (Sulu's icons, a green check for `ok`); the data may carry `limit` (rows before "Show all", default 10) and `header: false` (no table head, for key/value rows). `footer.title` may be left out. Texts may be `['text' => '8.4.27']` to be shown as they are, never taken for a translation key. What the list derives from its data lives in `src/Resources/js/list.js` and is tested with `node --test tests/js/*.test.mjs`.
- New requirement `symfony/process` (always present in a Sulu project); `doctrine/doctrine-migrations-bundle` and `symfony/messenger` only under `suggest`.

### Changed

- The tile `sulu_system` on the overview is small now: a traffic light with a row per problem (or "Everything is in order"), the age of the last check and a link "Details" to the tab "System". Its key stays, so saved arrangements keep working.
- For users with the system permission the dashboard is a tab view (`#/overview`, `#/system`) and the root path `/` opens the overview, as with the tab "Visitors". Without the permission nothing changes.

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
