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

Ist `amoifr/sulu-log-stats-bundle` installiert und aktiviert und hat der Benutzer das Recht `sulu.settings.log_stats`, bekommt das Dashboard den Tab **Besucher** (die Statistik-Seite dieses Bundles mit seinen Optionen `apiUrl` und `timezone`) und die Kachel **Besucher (7 Tage)** in der schmalen Spalte: Seitenaufrufe der letzten sieben Tage, die Summe der täglichen Besucher und der Anteil der Fehlerseiten (Status 4xx und 5xx). Der Tab ersetzt den Menüpunkt „Statistik“ dieses Bundles (siehe oben). Die Log-Statistik ist keine Voraussetzung dieses Bundles (nur `suggest`); ohne sie wird nichts davon geladen.

Die Besucherstatistik stammt aus dem Bundle [amoifr/sulu-log-stats-bundle](https://github.com/Amoifr/sulu-log-stats-bundle) von Amoifr. Danke für ein so schlankes und datensparsames Bundle.
