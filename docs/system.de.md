# Der Tab „System“

Für Administratoren: was Aufmerksamkeit braucht, Versionen, Umgebung und das, was man nicht vergessen sollte. Der Tab erscheint nur bei Benutzern mit eigenem Recht, Redakteure sehen ihn nie. Auf der Übersicht steht davon nichts mehr.

![Der Tab System](img/dashboard-system.de.png)

## Aufbau

Der Tab besteht wie „Besucher“ aus Kacheln (Gruppe `system`). Sie lassen sich über „Anpassen“ anordnen, ausblenden und zurücksetzen. Die Anordnung wird je Benutzer gespeichert (`sulu_dashboard.layout.system`). Der Tab hat weder Zeitraum noch Sprachauswahl, die Werte hängen nicht davon ab.

| Kachel | Spalte | Inhalt |
|---|---|---|
| Zustand | breit, angepinnt, zuerst | eine Überschrift („Alles in Ordnung“ oder „3 Punkte brauchen Aufmerksamkeit“, in der Farbe des schlimmsten Befunds) und je Prüfung eine Zeile mit Symbol, siehe unten |
| Sicherheitslücken | breit, angepinnt | Tabelle aus `composer audit`, siehe unten. Ohne Funde die Meldung „Keine bekannten Sicherheitslücken.“ Darunter die nicht mehr gepflegten Pakete, falls Composer welche meldet. |
| Speicher | breit | Gesamtgröße und ein Balken je Ordner mit Anteil, siehe unten. Ohne Prüflauf der Hinweis, wie gemessen wird. |
| Versionen | schmal | PHP mit dem Ende der Sicherheitsunterstützung, Symfony, Sulu; ein neueres Patch-Release wird genannt, siehe unten |
| Umgebung | schmal | Modus (`prod`, `dev` ...), Debug-Modus, Speicherlimit, Laufzeitlimit, OPcache, Zeitzone, Standardsprache, siehe unten |

Zustand, Versionen und Umgebung sind Listen aus Fakten ohne Sulu-Tabellenzellen: keine Einrückung, kompakte Zeilen, links der Name, rechts der Wert, wo es hilft eine graue Zeile darunter, davor Sulus Symbole für in Ordnung, Warnung und Fehler. Eine Komponente zeichnet alle drei, damit sie gleich aussehen. Auch eine eigene Kachel kann sie nutzen: Sie ist die Darstellungsart `custom` mit dem Schlüssel der eigenen Kachel, siehe [Eigene Kachel](widgets.de.md).

### Die Kachel „Zustand“

Eine Zeile je Prüfung, in dieser Reihenfolge. Jede Zeile hat den grünen Haken, das gelbe Warnzeichen oder das rote Fehlerzeichen.

| Zeile | Grün | Gelb | Rot |
|---|---|---|---|
| Sicherheitslücken | keine bekannt | Prüfung nie gelaufen oder nicht möglich | gefunden (mit Zahl) |
| Pakete | alle gepflegt | einige werden nicht mehr gepflegt | |
| PHP-Support | bis 31.12.2028 | die Sicherheitsunterstützung endet innerhalb eines halben Jahres | sie ist beendet |
| Debug-Modus | aus (in `dev` auch an) | | an in `prod`: Besucher könnten interne Details sehen (`APP_DEBUG=0`, danach den Cache leeren) |
| Datenbank-Migrationen | alle ausgeführt | offene (mit Zahl) oder nicht lesbar | |
| Fehlgeschlagene Nachrichten | keine | welche (mit Zahl) oder nicht zählbar | |
| Cache, Logs | | nur als Zeile, wenn `var/cache` oder `var/log` über dem Schwellenwert liegt | |
| Letzte Prüfung | Alter der Hintergrundprüfung | nie oder älter als `max_age`, immer mit dem Befehl als Text | |

Migrationen und fehlgeschlagene Nachrichten haben nur dort eine Zeile, wo es etwas zu lesen gibt (`doctrine/doctrine-migrations-bundle`, ein `failure_transport` von Symfony Messenger; nur lesend, nichts wird ausgeführt, wiederholt oder gelöscht). Die Überschrift zählt jede Zeile, die nicht grün ist.

### Die Kacheln „Versionen“ und „Umgebung“

- **PHP**: die Version und darunter „Sicherheitsupdates bis 31.12.2028“. Die Enddaten stehen in einer festen Tabelle im Code (Quelle: php.net/supported-versions.php, `PhpSupport::SECURITY_UNTIL`), online wird nichts gefragt. Ein Test schlägt fehl, sobald das PHP der Tests nicht in der Tabelle steht: Dann gehört die neue Minor-Version hinein. Versionen, die die Tabelle nicht kennt, bekommen keine Aussage.
- **Neueres Patch-Release**: Findet `composer outdated --direct --patch-only` eine neuere Patch-Version von Sulu oder Symfony (`symfony/framework-bundle`), steht in der Zeile „Patch-Update verfügbar: 3.0.11“ mit gelbem Symbol. Ein Patch-Release bleibt innerhalb der Version, die das Projekt gewählt hat, ist also die sichere Art von Update. Composer fragt dafür das Internet (rund zehn Sekunden), deshalb läuft das nur im Konsolenbefehl. Ohne Internet oder ohne Composer sagen die Zeilen einfach nichts (die Konsolenausgabe und die Antwort des URL-Crons nennen dann den Grund). Geprüft werden nur direkte Abhängigkeiten.
- **Umgebung**: die Live-Werte des laufenden PHP: Modus, Debug-Modus, `memory_limit`, `max_execution_time`, OPcache (**gelb in `prod`, wenn er aus ist**), Zeitzone und die Standardsprache des Kernels. Keine Pfade, keine Schlüssel, keine Verbindungsdaten.

### Die Kachel „Speicher“

Die Kachel beantwortet: Wo bleibt der Platz, und wächst etwas ungewöhnlich? Sie zeigt

- die **Gesamtgröße** der gemessenen Ordner und darunter für jeden Bereich einen **Balken mit Größe und Anteil**: `public/uploads` (erzeugte Bildformate und Uploads), `var/cache`, `var/log`, `var/storage` (Dateispeicher der Medien) und jeden weiteren Ordner in `var`. Lose Dateien in `var` stehen als „Sonstige Dateien in var“. Die sechs größten Bereiche bekommen einen Balken, der Rest steht zusammen in einer Zeile („3 weitere Ordner“).
- die **Veränderung seit der letzten Messung**, zum Beispiel „+12,0 MB seit der Messung vor 1 Tag“, für die Summe und je Ordner. Änderungen unter 1 MB bleiben unerwähnt. Der Vergleich ist immer die Messung davor; läuft der Befehl mehrmals am Tag, ist das Fenster kürzer, und die Kachel nennt es.
- bei **Medien** die Zahl der Medien und die Größe der Originale. Sie stammen aus Sulus Mediatabellen (`me_media`, `me_file_versions`) und werden nur im Befehl gelesen. Fehlen die Tabellen, entfällt die Zeile.
- einen **Hinweis**, wenn `var/cache` oder `var/log` größer als der Schwellenwert ist (der Balken wird gelb): „Der Cache ist 1,9 GB groß. Leeren mit cache:clear …“ bzw. „Alte Logdateien rotieren oder löschen.“ Der Hinweis erscheint auch als Warnzeile in der Kachel „Zustand“.
- immer das **Alter der Messung**: „Gemessen vor 3 Stunden vom Befehl sulu:dashboard:system-check, nicht live.“ Ist die Messung älter als `max_age`, steht dort stattdessen die Warnung, dass der Cronjob vermutlich nicht mehr läuft.

Bewusst weggelassen: **freier Plattenplatz**. Auf Shared Hosting meldet PHP den Platz der ganzen Serverplatte, nicht das Kontingent des Kontos; eine solche Zahl wäre irreführend. Ebenso die Gesamtgröße des Projekts (`vendor`, `node_modules` zu zählen dauert zu lang) und die größten Medien-Sammlungen.

Die Summe ist die der gemessenen Ordner, nicht die Größe der Platte.

### Die Tabelle der Sicherheitslücken

Eine Zeile je Meldung, die schlimmste zuerst (kritisch, hoch, mittel, niedrig, ohne Angabe):

| Spalte | Quelle in `composer audit --format=json` |
|---|---|
| Paket | `packageName` |
| Version | installierte Version des Pakets im Projekt (aus Composers `InstalledVersions`, nicht Teil der Audit-Ausgabe) |
| Kennung | `cve`, sonst `advisoryId` (zum Beispiel `PKSA-…`) |
| Beschreibung | `title`, als Link auf `link` (öffnet in einem neuen Tab, `rel="noopener noreferrer"`). Nur `http`- und `https`-Adressen werden zu Links, alles andere bleibt Text. |
| Schweregrad | `severity` (`critical`, `high`, `medium`, `low`), oft leer: dann „–“ |
| Gemeldet am | `reportedAt`, nur das Datum |

Zehn Zeilen sind sichtbar, der Rest per „Alle anzeigen“. Gespeichert werden höchstens 200 Meldungen und nur diese Felder (nicht `sources`, `affectedVersions` oder die rohe Ausgabe). Nicht mehr gepflegte Pakete kommen aus `abandoned` (Paket, Ersatz).

## Auf der Übersicht steht nichts mehr

Die kleine Ampel `sulu_system` hat die Übersicht verlassen, ihr Inhalt ist die erste Kachel des Tabs („Zustand“). Gespeicherte Anordnungen der Übersicht, die noch `sulu_system` nennen, schaden nicht: Ein Schlüssel, den keine Kachel mehr hat, wird übergangen. `disabled_widgets: ['sulu_system']` bleibt gültig und tut nichts. Die Kacheln „Letzte Prüfung“, „Datenbank-Migrationen“ und „Fehlgeschlagene Nachrichten“ der ersten Tab-Versionen sind jetzt Zeilen des Zustands, ihre gespeicherten Plätze werden genauso übergangen.

Es wird nichts Geheimes gezeigt: keine Verbindungsdaten, Schlüssel oder absoluten Pfade, nur Versionen, Zahlen, Paketnamen und die relativen Ordnernamen. Eine Prüfung, die scheitert, zeigt nie die Fehlermeldung, nur einen kurzen festen Text. Einzelheiten stehen im Log.

## Recht

Unter Einstellungen → Benutzerrollen → Einstellungen das Recht `sulu.settings.dashboard_system` („Ansehen“). Es ist unabhängig von `sulu.settings.dashboard`: Eine Rolle braucht beide, um den Tab zu sehen. Ohne das Recht gibt es keinen Tab „System“ und keine Tab-Leiste, wenn er der einzige wäre. Zunächst hat keine Rolle es: den Haken nur bei der Administrator-Rolle setzen.

## Die Hintergrundprüfung (Cron)

`composer audit` und `composer outdated` fragen das Internet, und das Zählen der Ordner kann dauern. Beides gehört nicht in einen Seitenaufruf, deshalb erledigt es ein Konsolenbefehl und legt das Ergebnis für den Tab ab:

```bash
php bin/adminconsole sulu:dashboard:system-check
```

Einmal am Tag ausführen, zum Beispiel mit diesem Cron-Eintrag (Pfad anpassen):

```
15 4 * * * cd /pfad/zum/projekt && php bin/adminconsole sulu:dashboard:system-check >> var/log/system-check.log 2>&1
```

- `bin/adminconsole` oder `bin/websiteconsole` verwenden: Den Befehl gibt es in beiden Kontexten, und er speichert an derselben Stelle (siehe unten). Der Tab zeigt das Ergebnis also in jedem Fall.
- Eine gescheiterte Prüfung endet mit Exit-Code 1 (Composer nicht gefunden, kein Internet, zu langsam), eine gefundene Lücke nicht: Die Prüfung selbst hat funktioniert. Der Grund bleibt nicht verborgen: Die Ausgabe nennt „Composer said: ...“ mit der Fehlermeldung von Composer (höchstens 300 Zeichen; Farben, der Projektpfad, andere Pfade und alles, was nach Geheimnis aussieht, sind entfernt), und die Kachel „Sicherheitslücken“ zeigt sie unter der Meldung. Bei den Updates ist es die Zeile „Updates: could not be checked: ...“.
- **Solange der Befehl nie gelaufen ist, steht im Zustand „nicht geprüft“ und „nie“**, und der Tab zeigt weder Sicherheitslücken noch Ordnergrößen. Das Ergebnis liegt im Speicherordner (siehe unten) und enthält auch die Messung davor (für die Veränderung). Ist der letzte Lauf älter als 48 Stunden, warnt der Tab, dass der Cronjob stehen geblieben sein könnte (`sulu_dashboard.system.max_age`, in Stunden, bei einem wöchentlichen Cronjob zum Beispiel 200).
- `cache:clear` löscht das Ergebnis nicht, ein Deployment, das die Cache-Ordner ersetzt, ebenso wenig: Das Ergebnis liegt in einem eigenen Ordner, `var/sulu-dashboard` (siehe „Wo das Ergebnis liegt“). Die Veränderung seit der letzten Messung bleibt deshalb über ein Deployment erhalten.
- Hosting ohne crontab: siehe „Ohne crontab: Systemcheck per URL-Cron“ weiter unten.
- Composer muss für den Benutzer des Cronjobs aufrufbar sein. Findet der Befehl Composer unter diesem Namen nicht, den Pfad eintragen: `sulu_dashboard.system.composer_binary: '/usr/local/bin/composer'` oder den Pfad zu einer `composer.phar`. Ohne `composer.lock` im Projekt gibt es nichts zu prüfen. Composer braucht außerdem ein Home- und ein Cache-Verzeichnis: Wo die Umgebung keines mitbringt (kein `HOME`, unter Windows kein `APPDATA`/`LOCALAPPDATA`; typisch für Cron und für das PHP eines Webservers), startet das Bundle Composer mit `COMPOSER_HOME` und `COMPOSER_CACHE_DIR` in einem eigenen Ordner `var/sulu-dashboard/composer`. Ein brauchbares Home und ein vom Server gesetztes `COMPOSER_HOME` reicht das Bundle ausdrücklich an Composer weiter, denn ein Webserver trägt `APPDATA` oder `HOME` nicht in `$_SERVER` ein und Symfony Process gibt nur solche Variablen weiter. Ein vom Server gesetztes `COMPOSER_HOME` bleibt unangetastet; `sulu_dashboard.system.composer_home` legt einen Ordner fest, der immer genutzt wird.

### Wo das Ergebnis liegt

Das Ergebnis liegt in einem eigenen Cache-Pool (`sulu_dashboard.system_cache`, ein `FilesystemAdapter`) im Ordner `var/sulu-dashboard` des Projekts. Es gehört nicht in `cache.app`: Sulu gibt jedem Kontext einen eigenen Cache-Ordner (`var/cache/admin`, `var/cache/website`), ein Ergebnis in `cache.app` wäre für den anderen Kontext unsichtbar und würde von `cache:clear` gelöscht. Der eigene Ordner wird von beiden Kontexten gelesen und geschrieben und übersteht `cache:clear`. Er braucht keine Einrichtung, der Befehl legt ihn an; er muss für den Benutzer des Webservers und des Cronjobs beschreibbar sein. Wo das nicht geht, den Ordner ändern:

```yaml
sulu_dashboard:
    system:
        storage_dir: '%kernel.project_dir%/var/sulu-dashboard'   # Standard
```

Ergebnisse, die frühere Versionen in `cache.app` abgelegt haben, werden nicht mehr gelesen; nach dem Update steht im Tab „nie“, bis der Befehl einmal gelaufen ist. Es geht nichts kaputt.

### Ohne crontab: Systemcheck per URL-Cron

Manches Shared Hosting kennt nur einen „URL-Cron“: Der Anbieter ruft zu festen Zeiten eine Webadresse auf. Dafür gibt es den Befehl auch im Website-Kontext, ein kleiner Controller des Projekts kann ihn also starten. Das Beispiel nutzt ein Geheimnis in der URL (in einer Umgebungsvariable) und führt den Befehl im laufenden Kernel aus:

```php
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

final class SystemCheckCronController
{
    public function __construct(private readonly string $cronToken)
    {
    }

    #[Route('/cron/system-check/{token}', methods: ['GET'])]
    public function __invoke(KernelInterface $kernel, string $token): Response
    {
        if (!hash_equals($this->cronToken, $token)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        set_time_limit(300);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArrayInput(['command' => 'sulu:dashboard:system-check']), $output);

        return new Response($output->fetch(), 0 === $exitCode ? 200 : 500, ['Content-Type' => 'text/plain']);
    }
}
```

Den Controller wie jeden anderen des Projekts registrieren und mit `$cronToken` versorgen (zum Beispiel `'%env(SYSTEM_CHECK_CRON_TOKEN)%'`). In der Cron-Funktion des Anbieters die Adresse `https://example.org/cron/system-check/<geheimnis>` eintragen (einmal am Tag genügt). Die Route nicht in die Sitemap und nicht in den Cache aufnehmen, die Adresse nicht protokollieren.

Zwei Dinge braucht das Web-PHP:

- **Netzzugang**: Composer fragt packagist.org. Hat das Web-PHP keinen Zugang (Firewall, Proxy, PHP ohne `openssl`), meldet die Sicherheitsprüfung „Composer kann die Sicherheitsdatenbank nicht abrufen. Netzzugang des Web-PHP prüfen oder den Befehl per SSH ausführen.“, und die Updates entfallen. Proxy-Einstellungen (`HTTPS_PROXY`) übernimmt Composer aus der Umgebung des PHP.
- `proc_open` darf nicht in `disable_functions` stehen: Die Prüfung startet Composer (`composer audit`, `composer outdated`) als Prozess. Ist es gesperrt, lässt sich Composer nicht starten: Der Tab meldet die Sicherheitsprüfung dann als „konnte nicht laufen“ (die Ordnergrößen werden trotzdem gespeichert).
- Composer muss für das PHP des Webservers aufrufbar sein. Oft gibt es den Befehl `composer` dort nicht. Dann `sulu_dashboard.system.composer_binary` auf den Pfad einer `composer.phar` im Projekt setzen. Ein fehlendes Home-Verzeichnis des Accounts (`HOME`, unter Windows `APPDATA`) braucht keine Einstellung: Composer bekommt einen eigenen Ordner `var/sulu-dashboard/composer` (siehe „Die Prüfung im Hintergrund“). Ohne ihn bräche Composer mit „The APPDATA or COMPOSER_HOME environment variable must be set“ ab. Das Web-PHP hat außerdem eine `max_execution_time`: Der Befehl braucht etwa zehn bis dreißig Sekunden, `set_time_limit()` im Controller hilft, wo der Anbieter es erlaubt.

## Prüfen ohne Login

```bash
php bin/adminconsole sulu:dashboard:system-check
php bin/adminconsole sulu:dashboard:dump --user=admin --widget=sulu_system_status
php bin/adminconsole sulu:dashboard:dump --user=admin --group=system
```

Der Benutzer braucht das Recht `sulu.settings.dashboard_system`, sonst fehlen die Kacheln in der Ausgabe (genauso wie im Browser).

## Konfiguration

```yaml
sulu_dashboard:
    system:
        composer_binary: composer   # Befehl im PATH oder Pfad zu einer composer.phar
        composer_home: ~            # Ordner für Home und Cache von Composer; null: Composers eigener, var/sulu-dashboard/composer nur, wo die Umgebung keinen hat
        storage_dir: '%kernel.project_dir%/var/sulu-dashboard'   # Ordner des Ergebnisses (beide Kontexte, von cache:clear nicht gelöscht)
        max_age: 48                 # Stunden, nach denen die letzte Prüfung als zu alt gilt
        cache_limit: 1024           # MB: ab dieser Größe von var/cache gibt die Kachel „Speicher“ einen Hinweis (0 = aus)
        log_limit: 512              # MB: dasselbe für var/log (0 = aus)
    disabled_widgets: ['sulu_system_environment', 'sulu_system_storage']   # schaltet Kacheln ab (Übersicht und Tab)
```
