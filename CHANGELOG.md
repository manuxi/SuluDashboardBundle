# Changelog

## Unreleased

First version of SuluDashboardBundle.

### Added

- Start page of the admin (view `sulu_dashboard.dashboard` on `/`, menu item "Dashboard") with the toolbar language select, a row of figures and two columns
- Interface `DashboardWidgetInterface` (plus optional `ConditionalDashboardWidgetInterface`), collected by a compiler pass; autoconfiguration tags every implementation with `sulu_dashboard.widget`; services without the interface can be tagged by hand with `key`, `title` and `type` and are checked while the container is compiled
- Display types `number`, `progress`, `list`, `bars` and `custom` (JavaScript `widgetRegistry`)
- Endpoint `GET /admin/api/dashboard/widgets/{key}?locale=` per tile; errors stay in their tile
- Built-in tiles: pages, articles, snippets, media, open drafts, translations, recent activity
- Security context `sulu.settings.dashboard` (view); content figures respect webspace and article type permissions
- Command `sulu:dashboard:dump --user=<name>` prints the tiles of a user as JSON
- Configuration `sulu_dashboard.disabled_widgets`
- Translations in German, English and Dutch; documentation in German and English
