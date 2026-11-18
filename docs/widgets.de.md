# Eigene Kachel in 5 Minuten

Eine Kachel ist eine kleine PHP-Klasse. Sie braucht kein JavaScript, keine Route und kein Template: Das Dashboard findet die Klasse, lädt ihre Daten beim Öffnen der Seite und zeigt sie mit den Bausteinen von Sulu.

```
DashboardWidgetInterface
  getKey()              eindeutiger Schlüssel, klein, Ziffern, Unterstriche ("testimonials_stars")
  getTitle()            Übersetzungsschlüssel der Domain admin, oder Klartext
  getType()             number | progress | list | bars | chart | custom
  getSecurityContext()  Sulu-Sicherheitskontext, den der Benutzer braucht (Ansehen), oder null
  getColumn()           row | main | side | auto
  getPriority()         Reihenfolge in der Spalte, höhere zuerst
  getData($locale)      die Daten in der Form der Darstellungsart (siehe unten)
```

Das Interface ist bewusst klein und ändert sich nur in Major-Versionen.

## 1. Die Klasse

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

## 2. Anmelden

In einem Projekt ist nichts weiter nötig: Mit `autoconfigure: true` (Standard von Symfony für `src/`) wird jeder Service, der das Interface implementiert, zur Kachel.

Den Titel in `translations/admin.de.yaml` eintragen (`app.dashboard_projects: "Projekte"`). Fertig.

## Texte und Links in den Kacheldaten

- Jeder Text einer Kachel (Titel, Beschriftungen, Zeilen, Hinweise, `empty`, Footer) darf ein Übersetzungsschlüssel der Domain admin, Klartext oder `['key' => ..., 'parameters' => [...]]` sein. Was wie ein Schlüssel aussieht (Wörter mit Punkten ohne Leerzeichen, etwa `app.dashboard_projects`), läuft durch Sulus Übersetzung, ein unbekannter Schlüssel wird unverändert angezeigt. Alles andere, etwa `Berlin` oder `Halle 3`, bleibt unverändert.
- Ein Link ist nie eine URL, sondern eine Sulu-View mit Attributen: `['view' => 'sulu_page.page_edit_form', 'attributes' => ['id' => $id, 'webspace' => 'your_webspace', 'locale' => $locale]]`.
- Ein Fehler in `getData()` bleibt in seiner Kachel: Er wird protokolliert, die Kachel zeigt eine Meldung. Nichts wird still verschluckt, und die Meldung der Exception geht nie an den Browser.

## Die Darstellungsarten

### number

Eine Zahl mit Beschriftung. Die optionalen `parts` teilen sie nach Status auf; `state` ist ein Workflow-Status (`published`, `draft`, `unpublished`, `review`) und zeigt Sulus Veröffentlichungs-Punkt. Gedacht für `WidgetColumns::ROW`.

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

Werte gegen ein Maximum, als Ringe (Standard) oder Balken (`'display' => 'bar'`). `skin` eines Balkens: `progress`, `success`, `warning`, `error`.

```php
return [
    'text' => 'Zeichen in diesem Monat',
    'display' => 'ring',
    'items' => [
        ['label' => 'Zeichen', 'value' => 120000, 'max' => 500000, 'text' => '120.000 von 500.000'],
    ],
    // optional: darunter eine kurze Liste mit Links, zum Beispiel „Zuerst ergänzen“
    'footer' => ['title' => 'app.dashboard_add_first', 'rows' => [['title' => 'Über uns', 'icon' => 'su-webspace', 'link' => ['view' => '...', 'attributes' => []]]]],
];
```

Ein Text darf auch `['key' => 'app.dashboard_hint', 'parameters' => ['count' => 3]]` sein, für eine Übersetzung mit Parametern.

### list

Eine kurze Tabelle mit einem Link je Zeile (Stift-Button). Zehn Zeilen sind sichtbar, der Rest auf Klick. `text` ist eine optionale graue zweite Zeile unter dem Titel (zum Beispiel ein Ort), `meta` eine zweite Spalte. Ein optionaler `footer` funktioniert wie bei `progress`.

```php
return [
    'empty' => 'app.dashboard_nothing_to_do', // erscheint ohne Zeilen
    'rows' => [
        [
            'title' => 'Kontaktformular',
            'text' => 'Schnipsel im Fuß',
            'meta' => '12.11.2026',
            'state' => 'unpublished',
            'link' => ['view' => 'sulu_snippet.snippet.edit_tabs', 'attributes' => ['id' => $id, 'locale' => $locale]],
        ],
    ],
    'footer' => ['title' => ['key' => 'app.dashboard_unpublished', 'parameters' => ['count' => 34]], 'rows' => [['title' => 'Alle nicht veröffentlichten', 'link' => ['view' => 'app.list', 'attributes' => []]]]],
];
```

### bars

Balken, die Werte miteinander vergleichen, zum Beispiel Bewertungen. Der längste Balken ist der höchste Wert, sofern kein `max` angegeben ist.

```php
return [
    'items' => [
        ['label' => '5 Sterne', 'value' => 12],
        ['label' => '4 Sterne', 'value' => 5, 'link' => ['view' => 'app.testimonials_list', 'attributes' => []]],
    ],
];
```

### chart

Liniendiagramm (eine oder zwei Reihen) oder Balkendiagramm (einfach oder gestapelt), als SVG gezeichnet, ohne Bibliothek. Das Diagramm nutzt die Breite der Kachel, rundet die Achse auf glatte Werte (Null ist immer dabei), dünnt die Beschriftung der x-Achse bei vielen Werten aus, zeigt je Punkt oder Balken einen Tooltip, ab zwei Reihen eine Legende und die Zahlen als Tabelle unter "Daten als Tabelle anzeigen". Ohne Beschriftungen oder Reihen meldet die Kachel, dass es keine Einträge gibt.

```php
return [
    'kind' => 'line',                 // line (Standard) | bar
    'stacked' => false,               // nur bei bar mit mindestens zwei Reihen
    'labels' => ['1. Nov.', '2. Nov.', '3. Nov.'],
    'series' => [
        ['label' => 'Seitenaufrufe', 'values' => [120, 98, 143]],
        ['label' => 'Besucher', 'values' => [40, 35, 51], 'color' => 'green'],
    ],
    'unit' => 'ms',                   // optional, steht in Tooltips, Tabelle und über der Achse
];
```

- `values` enthält eine Zahl je Beschriftung. Was keine Zahl ist (`null`), ist in einer Linie eine Lücke und in einem Balken null.
- `color` ist optional: `blue`, `green`, `yellow`, `red`, `gray` (die Farben von Sulu) oder eine Hex-Farbe wie `#52b6ca`. Ohne Angabe nehmen die Reihen die fünf Farben in dieser Reihenfolge.
- Titel der Kachel, `label` einer Reihe und `unit` dürfen Klartext, ein Übersetzungsschlüssel des Admins oder `['key' => ..., 'parameters' => [...]]` sein. Die Beschriftungen der x-Achse erscheinen unverändert.
- Mehr als zwei Reihen funktionieren, am lesbarsten bleibt das Diagramm mit bis zu zwei (Linie) oder bis zu fünf (gestapelte Balken).

### custom

Eine eigene React-Komponente für alles, was die Arten oben nicht zeigen können. `getData()` liefert weiterhin die Daten. Die Komponente wird in der `widgetRegistry` unter dem Schlüssel der Kachel angemeldet (wie eine View in Sulus `viewRegistry`):

```js
import {widgetRegistry} from 'sulu-dashboard-bundle';
import Card from 'sulu-admin-bundle/components/Card';

function Projects({widget, data, locale, locales, router, options}) {
    return <Card>{data.value}</Card>;
}

widgetRegistry.add('projects', Projects);
```

Das Paket in `assets/admin/package.json` eintragen (`"sulu-dashboard-bundle": "file:../../vendor/manuxi/sulu-dashboard-bundle/src/Resources/js"`) und die eigene Datei in `app.js` nach `sulu-dashboard-bundle` importieren. Sulus `Card` als Rahmen nehmen, damit die Kachel zu den anderen passt.

## Platz und Rechte

- `WidgetColumns::ROW`: Zeile mit Kennzahlen oben, alle Kacheln gleich hoch.
- `WidgetColumns::MAIN` und `WidgetColumns::SIDE`: eine breite (zwei Drittel) und eine schmale Spalte, die Kacheln stehen untereinander. Ist eine Spalte leer, nutzt die andere die volle Breite. Die Spalte ist ein Wunsch, siehe „Ausgleich der Spalten“ weiter unten.
- `WidgetColumns::AUTO`: kein Wunsch. Die Kachel kommt in die Spalte, die gerade kürzer ist. Passend, wenn die Kachel in beide Spalten passt.
- Spalte wählen: Breites (Tabellen, Listen mit mehreren Spalten) gehört in `MAIN`, Schmales (kurze Listen, Balken, Ringe, Status) in `SIDE`. Die Kacheln einer Spalte stehen in der Reihenfolge von `getPriority()`, höhere zuerst.
- `getSecurityContext()`: Der Benutzer sieht die Kachel nur mit dem Recht „Ansehen“ für diesen Kontext. Der Daten-Endpunkt prüft es erneut, ohne Recht lassen sich also auch die Daten nicht abrufen. `null` heißt: jeder, der das Dashboard öffnen darf. Eigene Inhalte (Seiten, Einträge) filtert `getData()` nach den Rechten des Benutzers.
- Schlüssel mit `sulu_` am Anfang gehören den Kacheln dieses Bundles.
- Eine Kachel abschalten: `sulu_dashboard.disabled_widgets: ['sulu_activities']`.

## Eine Kachel, die nur manchmal erscheint

`ConditionalDashboardWidgetInterface` implementieren (ergänzt `isAvailable(): bool`), zum Beispiel wenn ein API-Schlüssel fehlt.

## Eine Kachel aus einem anderen Bundle (optionale Abhängigkeit)

Ein Bundle mit Kachel soll seinen Nutzern das Dashboard nicht aufzwingen. Das Dashboard steht nur unter `suggest`, die Kachel-Services werden nur geladen, wenn es installiert ist. `ContainerBuilder::willBeAvailable()` ist Symfonys Weg für genau diesen Fall:

```php
// composer.json des Bundles
"suggest": { "manuxi/sulu-dashboard-bundle": "Zeigt eine Kachel mit ... auf dem Dashboard" },
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

Ohne Dashboard wird die Klasse `StarsWidget` nie geladen, `implements DashboardWidgetInterface` verursacht also keinen Fehler. `willBeAvailable` ist nur wahr, wenn das Paket wirklich installiert ist, nicht schon, wenn es als Dev-Abhängigkeit eines anderen Pakets in `vendor/` liegt.

Ändert sich das Interface in einer Major-Version, kann ein Bundle alte Versionen per `conflict` ausschließen.

### Ganz ohne PHP-Abhängigkeit: das Tag

Auch ein Service ohne das Interface kann eine Kachel sein. Er braucht eine öffentliche Methode `getData(string $locale): array` und das Tag `sulu_dashboard.widget` mit den Attributen `key`, `title` und `type` (optional `column`, `priority`, `security_context`). Statt eines Attributs darf die Klasse auch die gleichnamige Methode haben (`getKey()`, ...). Ist das Dashboard nicht installiert, wird das Tag ignoriert.

```yaml
services:
    Your\Bundle\Dashboard\StarsWidget:
        tags:
            - { name: sulu_dashboard.widget, key: testimonials_stars, title: your_bundle.stars, type: bars, column: side, priority: 20, security_context: sulu.testimonials }
```

Der Compiler-Pass prüft das Tag beim Kompilieren des Containers (Schlüssel, Art, Spalte, Doppelte) und nennt in der Fehlermeldung den Service. Kacheln mit Interface werden bei der ersten Benutzung des Dashboards geprüft (und von `sulu:dashboard:dump`).

## Eine Kachel ohne Login prüfen

```bash
php bin/console sulu:dashboard:dump --user=admin --locale=de
php bin/console sulu:dashboard:dump --user=admin --widget=projects
php bin/console sulu:dashboard:dump --user=admin --group=visitors
```

Mit `--group` kommen die Kacheln eines Tabs aus Kacheln statt die der Übersicht. Der Befehl gibt die Kacheln, die der Benutzer sieht, mit ihren Daten als JSON aus. Ein Fehler in einer Kachel erscheint als `error` und setzt den Exit-Code auf 1.

## Ausgleich der Spalten

Die Kacheln laden einzeln, die beiden Spalten wären sonst unterschiedlich hoch und ließen eine Lücke. Deshalb gleicht das Dashboard sie einmal im Browser aus:

1. Es wartet, bis alle Kacheln ihre Daten haben und ihre Größe einen Moment lang ruhig war (höchstens fünf Sekunden).
2. Es misst die Kacheln. Solange am Ende der längeren Spalte eine Kachel steht, die kleiner ist als der Unterschied der beiden Spalten, wandert sie in die kürzere Spalte. Die Reihenfolge nach `getPriority()` bleibt in jeder Spalte erhalten.
3. Danach nichts mehr. Spätere Höhenänderungen (eine Liste wird aufgeklappt, „Alle anzeigen“) verschieben nichts mehr, damit beim Arbeiten nichts springt. Ist in den Systemeinstellungen „weniger Bewegung“ gewählt, erscheinen verschobene Kacheln ohne Einblenden.

Unter 520 px Fensterbreite stellt Sulus Raster die Spalten untereinander, dann wird nichts ausgeglichen.

- `isPinned()`: Eine Kachel mit `PinnedDashboardWidgetInterface`, die `true` liefert, wird nie in die andere Spalte verschoben. Die eingebauten Kacheln „Offene Entwürfe“ und „Letzte Aktivität“ sind festgesetzt, sie bleiben dort, wo man sie sucht. Für eine Kachel ohne PHP-Abhängigkeit gibt es das Tag-Attribut `pinned: true`.
- Ausschalten mit `sulu_dashboard.layout.balance: false`. Dann wird nichts gemessen: Jede Kachel bleibt in ihrer Spalte, Kacheln mit `auto` werden gleichmäßig nach Anzahl verteilt.
- Wer die Kacheln selbst angeordnet hat ([Dashboard anpassen](customize.de.md)), ist nicht betroffen: Diese Anordnung gewinnt. Kacheln, die seitdem dazugekommen sind, werden nach den obigen Regeln einsortiert.
- Eine Kachel mit eigener React-Komponente, die nach dem Einhängen weitere Daten lädt, sollte sie in der Prop `cache` ablegen (ein Objekt, das das Dashboard so lange behält, wie die Seite offen ist): Beim Verschieben in die andere Spalte wird die Komponente neu eingehängt, der Cache spart die zweite Abfrage.
