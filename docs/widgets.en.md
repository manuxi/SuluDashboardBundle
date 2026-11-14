# Your own tile in 5 minutes

A tile is a small PHP class. It needs no JavaScript, no route and no template: the dashboard finds the class, loads its data when the page opens and shows it with Sulu's own components.

```
DashboardWidgetInterface
  getKey()              unique key, lower case, digits, underscores ("testimonials_stars")
  getTitle()            translation key of the admin domain, or plain text
  getType()             number | progress | list | bars | custom
  getSecurityContext()  Sulu security context the user needs (view), or null
  getColumn()           row | main | side
  getPriority()         order inside the column, higher first
  getData($locale)      the data in the shape of the type (see below)
```

The interface is small on purpose and only changes in major versions.

## 1. The class

```php
<?php

namespace App\Dashboard;

use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;

final class ProjectsWidget implements DashboardWidgetInterface
{
    public function __construct(private readonly ProjectRepository $projects)
    {
    }

    public function getKey(): string { return 'projects'; }
    public function getTitle(): string { return 'app.dashboard_projects'; }
    public function getType(): string { return WidgetTypes::NUMBER; }
    public function getSecurityContext(): ?string { return 'sulu.app.projects'; }
    public function getColumn(): string { return WidgetColumns::ROW; }
    public function getPriority(): int { return 5; }

    public function getData(string $locale): array
    {
        return ['value' => $this->projects->count()];
    }
}
```

## 2. Register it

In a project nothing else is needed: with `autoconfigure: true` (the Symfony default for `src/`) every service that implements the interface becomes a tile.

Add the title to `translations/admin.en.yaml` (`app.dashboard_projects: "Projects"`). That is all.

## Texts and links in tile data

- Every text of a tile (titles, labels, rows, notes, `empty`, footer) may be an admin translation key, plain text or `['key' => ..., 'parameters' => [...]]`. Text that looks like a key (words joined by dots without spaces, such as `app.dashboard_projects`) goes through Sulu's translation, a key that does not exist is shown as it is. Anything else, such as `Berlin` or `Hall 3`, is shown unchanged.
- A link is never a URL but a Sulu view with attributes: `['view' => 'sulu_page.page_edit_form', 'attributes' => ['id' => $id, 'webspace' => 'your_webspace', 'locale' => $locale]]`.
- An error in `getData()` stays inside its tile: it is logged and the tile shows a message. Nothing is swallowed silently, and the message of the exception is never sent to the browser.

## The display types

### number

A figure with a label. The optional parts split it by state; `state` is a workflow place (`published`, `draft`, `unpublished`, `review`) and shows Sulu's publish indicator. Meant for `WidgetColumns::ROW`.

```php
return [
    'value' => 42,
    'parts' => [
        ['label' => 'app.dashboard_live', 'count' => 40, 'state' => 'published'],
        ['label' => 'app.dashboard_with_changes', 'count' => 2, 'state' => 'draft'],
    ],
];
```

### progress

Values against a maximum, as rings (default) or bars (`'display' => 'bar'`). `skin` of a bar: `progress`, `success`, `warning`, `error`.

```php
return [
    'text' => 'Characters used this month',
    'display' => 'ring',
    'items' => [
        ['label' => 'Characters', 'value' => 120000, 'max' => 500000, 'text' => '120,000 of 500,000'],
    ],
    // optional: a short list of links below, for example "Add first"
    'footer' => ['title' => 'app.dashboard_add_first', 'rows' => [['title' => 'About us', 'icon' => 'su-webspace', 'link' => ['view' => '...', 'attributes' => []]]]],
];
```

A text may also be `['key' => 'app.dashboard_hint', 'parameters' => ['count' => 3]]` for a translation with parameters.

### list

A short table with a link per row (pen button). Ten rows are shown, the rest on demand. `text` is an optional grey second line under the title (for example a place), `meta` a second column. An optional `footer` works as with `progress`.

```php
return [
    'empty' => 'app.dashboard_nothing_to_do', // shown without rows
    'rows' => [
        [
            'title' => 'Contact form',
            'text' => 'Footer snippet',
            'meta' => '12 Nov 2026',
            'state' => 'unpublished',
            'link' => ['view' => 'sulu_snippet.snippet.edit_tabs', 'attributes' => ['id' => $id, 'locale' => $locale]],
        ],
    ],
    'footer' => ['title' => ['key' => 'app.dashboard_unpublished', 'parameters' => ['count' => 34]], 'rows' => [['title' => 'All unpublished', 'link' => ['view' => 'app.list', 'attributes' => []]]]],
];
```

### bars

Bars that compare values with each other, for example ratings. The longest bar is the highest value unless `max` is given.

```php
return [
    'items' => [
        ['label' => '5 stars', 'value' => 12],
        ['label' => '4 stars', 'value' => 5, 'link' => ['view' => 'app.testimonials_list', 'attributes' => []]],
    ],
];
```

### custom

Your own React component for everything the types above cannot show. `getData()` still delivers the data. The component is registered in the `widgetRegistry` under the key of the tile (like a view in Sulu's `viewRegistry`):

```js
import {widgetRegistry} from 'sulu-dashboard-bundle';
import Card from 'sulu-admin-bundle/components/Card';

function Projects({widget, data, locale, locales, router, options}) {
    return <Card>{data.value}</Card>;
}

widgetRegistry.add('projects', Projects);
```

Add the package to `assets/admin/package.json` (`"sulu-dashboard-bundle": "file:../../vendor/manuxi/sulu-dashboard-bundle/src/Resources/js"`) and import your file in `app.js` after `sulu-dashboard-bundle`. Use Sulu's `Card` as the frame, so the tile looks like the others.

## Place and permissions

- `WidgetColumns::ROW`: row of figures on top, all tiles of equal height.
- `WidgetColumns::MAIN` and `WidgetColumns::SIDE`: a wide (two thirds) and a narrow column, tiles stack. If one column is empty, the other uses the full width.
- Choosing the column: put wide content (tables, lists with several columns) in `MAIN`, narrow content (short lists, bars, rings, status) in `SIDE`. Tiles of a column stack in the order of `getPriority()`; the two columns are independent, so the shorter one ends earlier. Keep the heights of both columns similar, and do not leave one column with a single tiny tile. Without tiles in one column the other one takes the full width.
- `getSecurityContext()`: the user sees the tile only with the view permission for this context. The data endpoint checks it again, so a user without permission cannot request the data either. `null` means everyone who may open the dashboard. Content of its own (pages, entries) must be filtered by the permissions of the user inside `getData()`.
- Keys starting with `sulu_` belong to the tiles of this bundle.
- Switch a tile off in the configuration: `sulu_dashboard.disabled_widgets: ['sulu_activities']`.

## A tile that is only shown sometimes

Implement `ConditionalDashboardWidgetInterface` (adds `isAvailable(): bool`), for example when an API key is missing.

## A tile from another bundle (optional dependency)

A bundle that brings a tile should not force the dashboard on its users. Put the dashboard only under `suggest` and load the tile services only when it is installed. `ContainerBuilder::willBeAvailable()` is Symfony's way for exactly this:

```php
// composer.json of your bundle
"suggest": { "manuxi/sulu-dashboard-bundle": "Shows a tile with ... on the dashboard" },
"require-dev": { "manuxi/sulu-dashboard-bundle": "^1.0" }
```

```php
// DependencyInjection/YourBundleExtension.php
use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

public function load(array $configs, ContainerBuilder $container): void
{
    // ...
    if (ContainerBuilder::willBeAvailable('manuxi/sulu-dashboard-bundle', DashboardWidgetInterface::class, ['your-vendor/your-bundle'])) {
        $loader->load('dashboard.yaml');
    }
}
```

```yaml
# Resources/config/dashboard.yaml
services:
    Your\Bundle\Dashboard\StarsWidget:
        autoconfigure: true
        arguments: ['@your_bundle.repository']
```

Without the dashboard the class `StarsWidget` is never loaded, so `implements DashboardWidgetInterface` causes no error. `willBeAvailable` is only true when the package is really installed, not when it merely lies in `vendor/` as a dev dependency of another package.

If the interface changes in a major version, a bundle can exclude old versions with `conflict`.

### Without any PHP dependency: the tag

A service that does not implement the interface can still be a tile. It needs a public method `getData(string $locale): array` and the tag `sulu_dashboard.widget` with the attributes `key`, `title` and `type` (optional: `column`, `priority`, `security_context`). Instead of an attribute, the class may also have the method of the same name (`getKey()`, ...). If the dashboard is not installed, the tag is ignored.

```yaml
services:
    Your\Bundle\Dashboard\StarsWidget:
        tags:
            - { name: sulu_dashboard.widget, key: testimonials_stars, title: your_bundle.stars, type: bars, column: side, priority: 20, security_context: sulu.testimonials }
```

The compiler pass checks the tag while the container is compiled (key, type, column, duplicates) and names the service in the error message. Tiles that implement the interface are checked when the dashboard is first used (and by `sulu:dashboard:dump`).

## Checking a tile without a login

```bash
php bin/console sulu:dashboard:dump --user=admin --locale=en
php bin/console sulu:dashboard:dump --user=admin --widget=projects
```

The command prints the tiles the user sees with their data as JSON. An error in a tile appears as `error` and sets the exit code to 1.
