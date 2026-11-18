# Tabs: eine ganze Seite als Tab des Dashboards

Ein Bundle kann eine ganze Seite (nicht nur eine Kachel) aufs Dashboard bringen. Mit mindestens einem Tab wird das Dashboard zur Tab-View, wie Sulu sie für Personen und Organisationen nutzt: Der Tab „Übersicht“ zeigt die Kacheln, jeder Anbieter ergänzt einen Tab. Ohne Anbieter (oder wenn der Benutzer keinen sehen darf) gibt es keine Tab-Leiste, das Dashboard ist eine einzelne View.

```php
use Manuxi\SuluDashboardBundle\Tab\DashboardTabProviderInterface;

final class VisitorsTab implements DashboardTabProviderInterface
{
    public function getKey(): string { return 'visitors'; }                 // Teil des Pfads
    public function getTitle(): string { return 'app.visitors'; }           // Übersetzungsschlüssel oder Text
    public function getViewType(): string { return 'app.visitors_view'; }   // Schlüssel in Sulus viewRegistry
    public function getViewOptions(): array { return ['apiUrl' => '/admin/api/visitors']; }
    public function getSecurityContext(): ?string { return 'sulu.app.visitors'; }
    public function getPriority(): int { return 0; }                        // höher zuerst, nach „Übersicht“
}
```

- Die View ist wie üblich eine React-View, die in Sulus `viewRegistry` angemeldet wird (`viewRegistry.add('app.visitors_view', Visitors)`). Ihre Optionen liest sie aus `router.route.options`.
- Mit `autoconfigure: true` wird der Service automatisch gefunden (Tag `sulu_dashboard.tab`). Ein Bundle, das dieses Bundle nur vorschlägt (`suggest`), lädt den Service wie eine Kachel: mit `ContainerBuilder::willBeAvailable()` (siehe [Eigene Kachel in 5 Minuten](widgets.de.md)).
- Der Benutzer sieht den Tab nur mit dem Recht „Ansehen“ für `getSecurityContext()`.
- Schlüssel müssen eindeutig sein; `overview`, `dashboard` und `tabs` sind reserviert.

## Ein Tab aus Kacheln

Statt einer eigenen View kann ein Tab Kacheln zeigen. Sie werden im selben Modus „Anpassen“ angeordnet wie die Übersicht (siehe [Das Dashboard anpassen](customize.de.md)), und jeder Tab speichert seine Anordnung für sich.

1. Den Kacheln eine Gruppe geben: `GroupedDashboardWidgetInterface` implementieren (erweitert das Kachel-Interface, eine Methode `getGroup()`). Eine Kachel mit Gruppe erscheint nur im Tab dieser Gruppe; die Übersicht zeigt nur Kacheln ohne Gruppe.
2. Für den Tab `TilesTabProviderInterface` implementieren (erweitert das Tab-Interface):

```php
use Manuxi\SuluDashboardBundle\Admin\DashboardAdmin;
use Manuxi\SuluDashboardBundle\Tab\TilesTabProviderInterface;

final class VisitorsTab implements TilesTabProviderInterface
{
    // Schlüssel, Titel, Security-Kontext und Priorität wie bei jedem Tab ...
    public function getViewType(): string { return DashboardAdmin::VIEW; } // wird ignoriert, das Dashboard setzt ihn
    public function getViewOptions(): array { return []; }                 // optionale zusätzliche Optionen

    public function getGroup(): string { return 'visitors'; }              // die Kacheln dieser Gruppe
    public function getPeriods(): array { return [7, 30, 90, 365]; }       // [] = kein Zeitraum
    public function getDefaultPeriod(): int { return 30; }
    public function showLocale(): bool { return false; }                   // Sprachauswahl in der Toolbar
}
```

Das Dashboard baut daraus die View: Typ `sulu_dashboard.dashboard`, die Kacheln der Gruppe, der Speicherschlüssel `sulu_dashboard.layout.<gruppe>` für die Anordnung und die Optionen unten. Optionen aus `getViewOptions()` haben Vorrang.

### Zeitraum

Mit Zeiträumen bekommt die Toolbar des Tabs eine Auswahl („Letzte 7 Tage“ … und „Eigener Zeitraum …“). Ein eigener Zeitraum öffnet über den Kacheln eine Leiste mit zwei Datumsfeldern (Von, Bis) und „Übernehmen“. Ein Starttag nach dem Endtag, ein fehlender Tag oder mehr als 366 Tage (Grenze der Log-Statistik-API) zeigen in der Leiste einen Fehler und ändern nichts.

- Die Wahl wird je Tab sofort als Benutzereinstellung gespeichert (`sulu_dashboard.layout.<gruppe>.period`) und ist nach dem Neuladen noch da.
- Jede Kachel des Tabs bekommt die Prop `period`: `{days: 30}` (die letzten Tage bis heute) oder `{from: '2026-10-01', to: '2026-10-31'}` (Tage als `YYYY-MM-DD`, beide eingeschlossen). Ändert sich der Zeitraum, mountet das Dashboard die Kacheln neu; eine Kachel, die Daten in `props.cache` hält, muss den Zeitraum in den Schlüssel aufnehmen.
- Ohne `getPeriods()` gibt es keine Auswahl und keine Prop `period`. Die Übersicht hat keinen Zeitraum.

### Sprachauswahl

`showLocale(): false` blendet die Sprachauswahl des Tabs aus, für Kacheln, deren Zahlen nicht von der Sprache abhängen.

## Einen Menüpunkt ersetzen

Ein Tab, der eine Seite mitbringt, die schon einen eigenen Menüpunkt hat, kann dessen Platz einnehmen. Das Menü hat dann einen Eintrag weniger. Dazu `ReplacesNavigationItemInterface` implementieren (erweitert das Tab-Interface):

```php
public function getReplacedNavigationItem(): string { return 'other_bundle.statistics'; } // Name seines NavigationItem
public function getReplacedViews(): array { return ['other_bundle.dashboard']; }          // seine Views
```

Das Dashboard läuft nach allen anderen Admin-Klassen und entfernt den Menüpunkt, wenn der Tab für den Benutzer sichtbar ist. Die Views des ersetzten Menüpunkts bleiben unverändert, Direktlinks funktionieren also weiter, und der Menüpunkt „Dashboard“ leuchtet dort. Ersetzt werden können nur Menüpunkte der obersten Ebene. Abschalten mit `sulu_dashboard.tabs.replace_navigation: false`, dann bleibt der Menüpunkt stehen.

## Pfade

Sulu setzt den Pfad des Elternteils vor den Pfad eines Kindes. Das Dashboard übernimmt das für Sie:

| `start_page` | Tab-View | Übersicht | Tab `visitors` |
|---|---|---|---|
| `true` (Standard) | `/` | `/overview` | `/visitors` |
| `false` | `/dashboard` | `/dashboard/overview` | `/dashboard/visitors` |

Wer `/` öffnet, landet auf „Übersicht“. Sulus Suche bleibt auf `/search`. Vorhandene Direktlinks eines Bundles mit eigener Seite (zum Beispiel `/log-stats`) bleiben gültig.

## Eingebaut: Besucher aus der Log-Statistik

![Der Tab Besucher](img/dashboard-visitors.de.png)

Ist `amoifr/sulu-log-stats-bundle` (Version `~0.1`) installiert und aktiviert und hat der Benutzer das Recht `sulu.settings.log_stats`, bekommt das Dashboard den Tab **Besucher** und die Kachel **Besucher (7 Tage)** in der schmalen Spalte (Seitenaufrufe der letzten sieben Tage, Summe der täglichen Besucher, Anteil der Fehlerseiten mit Status 4xx und 5xx). Der Tab ersetzt den Menüpunkt „Statistik“ dieses Bundles (siehe oben). Die Log-Statistik ist keine Voraussetzung dieses Bundles (nur `suggest`); ohne sie wird nichts davon geladen.

### Der Tab aus Kacheln (Standard)

Der Tab ist ein Tab aus Kacheln (siehe oben, Gruppe `visitors`), mit Zeitraum (7, 30, 90, 365 Tage oder „Eigener Zeitraum …“) und dem Modus „Anpassen“: Kacheln ziehen, ausblenden, zurücksetzen, getrennt von der Übersicht gespeichert.

| Bereich | Kacheln |
|---|---|
| Zeile | Seitenaufrufe, Besucher, Anfragen, Bot-Anteil, Client-Fehler (4xx), Server-Fehler (5xx), Antwortzeit (mit 95. Perzentil), gesendete Daten |
| Diagramme | Seitenaufrufe pro Tag, Besucher pro Tag, Anfragen pro Tag nach Status (gestapelt), Seitenaufrufe nach Tageszeit, Antwortzeit pro Tag |
| Listen | Meistbesuchte Seiten, häufigste 404-Fehler, Seiten mit Serverfehlern |
| Tabelle | Zahlen pro Tag |

Die Besucher werden je Tag gezählt und addiert: Wer an einem anderen Tag wiederkommt, zählt erneut. Alle Kacheln teilen sich eine Anfrage an `GET /admin/api/log-stats` je Zeitraum. Die Diagramme zeichnet die Darstellungsart `chart` dieses Bundles (siehe [Eigene Kachel in 5 Minuten](widgets.de.md)).

Die Seite des Log-Statistik-Bundles selbst wird ersetzt: Außer dem Menüpunkt entfällt auch ihre View (`amoifr_log_stats.dashboard`), und ein Aufruf ihres Pfads (`#/log-stats`) leitet auf den Tab „Besucher“ um. Das passiert nur zusammen mit dem ersetzten Menüpunkt (`sulu_dashboard.tabs.replace_navigation: true`, Standard).

```yaml
sulu_dashboard:
    log_stats:
        tab: tiles   # tiles (Standard): Tab aus Kacheln. view: die Seite des anderen Bundles unverändert (technischer Rückfall, ohne Anpassen)
```

### Wenn sich die Schnittstelle ändert

Das Log-Statistik-Bundle ist ein 0.x-Bundle; die Kacheln lesen nur dessen JSON (`totals`, `days`, `pageViewsByHourOfDay`, `topPages`, `topNotFound`, `topServerErrors`, `responseTime`). Fehlende Felder gelten als leer, aber nie stillschweigend: Eine Kachel, der erwartete Felder fehlen, zeigt „Die Log-Statistik liefert andere Daten als erwartet. Es fehlt: …“ mit den Namen der Felder. Die erwartete Form ist in `tests/fixtures/log-stats-response.json` festgehalten und wird mit `node --test tests/js/*.test.mjs` geprüft. Dieses Bundle schlägt deshalb nur die Version `~0.1` vor; bei einer neuen Hauptversion zuerst Fixture und Test an die neue Antwort anpassen.

Die Besucherstatistik stammt aus dem Bundle [amoifr/sulu-log-stats-bundle](https://github.com/Amoifr/sulu-log-stats-bundle) von Amoifr. Danke für ein so schlankes und datensparsames Bundle: Es liefert Import, Aggregation, das Datenschutzkonzept (gesalzene Tages-Hashes, nur Zähler) und die Schnittstelle, auf der der Tab aufbaut.
