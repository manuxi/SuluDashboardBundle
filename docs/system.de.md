# Die Kachel „System“

Für Administratoren: Versionen, Umgebung und das, was man nicht vergessen sollte. Die Kachel erscheint nur bei Benutzern mit eigenem Recht, Redakteure sehen sie nie.

## Was sie zeigt

| Zeile | Bedeutung |
|---|---|
| PHP, Symfony, Sulu | installierte Versionen |
| Umgebung, Debug-Modus | `prod`, `dev` ... und ob Debug an ist. **Debug-Modus an in `prod` erscheint als rote Warnung ganz oben**, weil Besucher dadurch interne Details sehen können (`APP_DEBUG=0`, danach den Cache leeren). |
| Sicherheitsprüfung | Ergebnis von `composer audit`: entweder „keine bekannten Lücken“ oder eine rote Zeile mit der Zahl der Lücken, darunter die betroffenen Pakete mit Titel und CVE (höchstens zehn) |
| Datenbank-Migrationen | nur mit `doctrine/doctrine-migrations-bundle`: „aktuell“ oder eine Warnung mit der Zahl offener Migrationen (nur lesend, es wird nichts ausgeführt) |
| Fehlgeschlagene Nachrichten | nur wenn Symfony Messenger einen `failure_transport` hat: „keine“ oder eine Warnung mit der Zahl (nur lesend, nichts wird wiederholt oder gelöscht) |
| `public/uploads`, `var` | Platz, den diese Ordner belegen |
| Letzte Prüfung | Alter der Hintergrundprüfung, siehe unten |

Es wird nichts Geheimes gezeigt: keine Verbindungsdaten, Schlüssel oder absoluten Pfade, nur Versionen, Zahlen, Paketnamen und die relativen Ordnernamen. Eine Prüfung, die scheitert, zeigt nie die Fehlermeldung, nur einen kurzen festen Text. Einzelheiten stehen im Log.

## Recht

Unter Einstellungen → Benutzerrollen → Einstellungen das Recht `sulu.settings.dashboard_system` („Ansehen“). Es ist unabhängig von `sulu.settings.dashboard`: Eine Rolle braucht beide, um die Kachel zu sehen. Zunächst hat keine Rolle es: den Haken nur bei der Administrator-Rolle setzen.

## Die Hintergrundprüfung (Cron)

`composer audit` fragt das Internet, und das Zählen der Ordner kann dauern. Beides gehört nicht in einen Seitenaufruf, deshalb erledigt es ein Konsolenbefehl und legt das Ergebnis für die Kachel ab:

```bash
php bin/adminconsole sulu:dashboard:system-check
```

Einmal am Tag ausführen, zum Beispiel mit diesem Cron-Eintrag (Pfad anpassen):

```
15 4 * * * cd /pfad/zum/projekt && php bin/adminconsole sulu:dashboard:system-check >> var/log/system-check.log 2>&1
```

- `bin/adminconsole` verwenden: Die Kachel liest den Cache des Admins, und der Befehl speichert dort.
- Eine gescheiterte Prüfung endet mit Exit-Code 1 (Composer nicht gefunden, kein Internet, zu langsam), eine gefundene Lücke nicht: Die Prüfung selbst hat funktioniert.
- **Solange der Befehl nie gelaufen ist, steht in der Kachel „Die Hintergrundprüfung ist noch nie gelaufen“** und sie zeigt weder Sicherheitsprüfung noch Ordnergrößen. Ist der letzte Lauf älter als 48 Stunden, warnt die Kachel, dass der Cronjob stehen geblieben sein könnte (`sulu_dashboard.system.max_age`, in Stunden, bei einem wöchentlichen Cronjob zum Beispiel 200).
- `cache:clear` löscht auch dieses Ergebnis. Den Befehl am Ende eines Deployments nach `cache:clear` ausführen.
- Hosting ohne crontab: die Cron-Funktion des Anbieters den Befehl starten lassen, oder ihn ab und zu von Hand ausführen. Ein Skript, das den Befehl über eine geheime URL aufruft, wäre nur eine Idee, das Bundle bringt keine solche Route mit.
- Composer muss für den Benutzer des Cronjobs aufrufbar sein. Findet der Befehl Composer unter diesem Namen nicht, den Pfad eintragen: `sulu_dashboard.system.composer_binary: '/usr/local/bin/composer'` oder den Pfad zu einer `composer.phar`. Ohne `HOME`-Verzeichnis (manche Cron-Umgebungen) braucht Composer vor dem Befehl `COMPOSER_HOME=/pfad/zu/einem/beschreibbaren/ordner`. Ohne `composer.lock` im Projekt gibt es nichts zu prüfen.

## Prüfen ohne Login

```bash
php bin/adminconsole sulu:dashboard:system-check
php bin/adminconsole sulu:dashboard:dump --user=admin --widget=sulu_system
```

Der Benutzer braucht das Recht `sulu.settings.dashboard_system`, sonst fehlt die Kachel in der Ausgabe (genauso wie im Browser).

## Konfiguration

```yaml
sulu_dashboard:
    system:
        composer_binary: composer   # Befehl im PATH oder Pfad zu einer composer.phar
        max_age: 48                 # Stunden, nach denen die letzte Prüfung als zu alt gilt
    disabled_widgets: ['sulu_system']   # schaltet die Kachel ganz ab
```
