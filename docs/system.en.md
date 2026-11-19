# The tab "System"

For administrators: what needs attention, versions, environment and the things that should not be forgotten. The tab only appears for users who have its own permission, so editors never see it. Nothing of it is on the overview.

![The tab System (the admin interface is shown in German)](img/dashboard-system.de.png)

## Layout

Like "Visitors", the tab is made of tiles (group `system`). With "Customize" they can be arranged, hidden and reset. The arrangement is saved per user (`sulu_dashboard.layout.system`). The tab has neither a period nor a language select, the values do not depend on them.

| Tile | Column | Content |
|---|---|---|
| Condition | wide, pinned, first | a headline ("Everything is in order" or "3 items need attention", in the colour of the worst finding) and a row with an icon per check, see below |
| Security vulnerabilities | wide, pinned | table from `composer audit`, see below. Without findings the message "No known security vulnerabilities." Below it the packages that are no longer maintained, if Composer reports any. |
| Storage | wide | total size and one bar per folder with its share, see below. Without a run the hint how it is measured. |
| Versions | narrow | PHP with the end of its security support, Symfony, Sulu; a newer patch release is named, see below |
| Environment | narrow | mode (`prod`, `dev` ...), debug mode, memory limit, time limit, OPcache, time zone, default language, see below |

Condition, Versions and Environment are lists of facts without Sulu table cells: no indent, compact rows, the name on the left, the value on the right, a grey line below where it helps, and Sulu's icons for ok, warning and error. One component draws all three, so they look the same. A tile of your own can use it too: it is the type `custom` with the key of your tile, see [Own tile](widgets.en.md).

### The tile "Condition"

One row per check, in this order. A row has the green check, the yellow warning or the red error sign.

| Row | Green | Yellow | Red |
|---|---|---|---|
| Security vulnerabilities | none known | check never ran, or could not run | found (with the number) |
| Packages | all maintained | some are no longer maintained | |
| PHP support | until 31 Dec 2028 | the security support ends within half a year | it has ended |
| Debug mode | off (in `dev` also on) | | on in `prod`: visitors could see internal details (`APP_DEBUG=0`, then clear the cache) |
| Database migrations | all have run | open ones (with the number), or not readable | |
| Failed messages | none | some (with the number), or not countable | |
| Cache, logs | | only as a row when `var/cache` or `var/log` is bigger than the threshold | |
| Last check | age of the background check | never, or older than `max_age`, always with the command as text | |

Migrations and failed messages have a row only where there is something to read (`doctrine/doctrine-migrations-bundle`, a `failure_transport` of Symfony Messenger; read only, nothing is run, retried or deleted). The headline counts every row that is not green.

### The tiles "Versions" and "Environment"

- **PHP**: the version and below it "Security updates until 31 Dec 2028". The end dates come from a fixed table in the code (source: php.net/supported-versions.php, `PhpSupport::SECURITY_UNTIL`), nothing is asked online. A test fails as soon as the PHP that runs the tests is not in the table: a new minor version has to be added then. Versions the table does not know get no statement.
- **Newer patch release**: if `composer outdated --direct --patch-only` finds a newer patch version of Sulu or Symfony (`symfony/framework-bundle`), the row says "Patch update available: 3.0.11" with a yellow icon. A patch release stays inside the version the project chose, so it is the safe kind of update. Composer asks the internet for this (about ten seconds), so it only runs in the console command. Without internet or without Composer the rows simply say nothing. Only direct requirements are checked.
- **Environment**: the live values of the running PHP: mode, debug mode, `memory_limit`, `max_execution_time`, OPcache (**yellow in `prod` if it is off**), time zone and the default language of the kernel. No paths, no keys, no connection data.

### The tile "Storage"

The tile answers: where does the space go, and is something growing unusually? It shows

- the **total size** of the measured folders and below it a **bar with size and share** for every area: `public/uploads` (generated image formats and uploads), `var/cache`, `var/log`, `var/storage` (file storage of the media) and every other folder in `var`. Loose files in `var` appear as "Other files in var". The six biggest areas get a bar, the rest is summed up in one row ("3 more folders").
- the **change since the last measurement**, for example "+12.0 MB since the measurement 1 day ago", for the total and per folder. A change below 1 MB is not mentioned. The comparison is always the measurement before; if the command runs several times a day the window is shorter, and the tile says so.
- for **media** the number of media and the size of the originals. They come from Sulu's media tables (`me_media`, `me_file_versions`) and are read by the command only. Without the tables the line is left out.
- a **hint** when `var/cache` or `var/log` is bigger than the threshold (the bar turns yellow): "The cache is 1.9 GB. Clear it with cache:clear ..." or "Rotate or delete old log files." The hint also appears as a warning row in the tile "Condition".
- always the **age of the measurement**: "Measured 3 hours ago by the command sulu:dashboard:system-check, not live." If the measurement is older than `max_age` the warning that the cron job has probably stopped replaces it.

Left out on purpose: **free disk space**. On shared hosting PHP reports the space of the whole server disk, not the quota of the account; such a number would mislead. Likewise the size of the whole project (counting `vendor` and `node_modules` takes too long) and the biggest media collections.

The total is the sum of the measured folders, not the size of the disk.

### The table of vulnerabilities

One row per report, the worst first (critical, high, medium, low, none given):

| Column | Source in `composer audit --format=json` |
|---|---|
| Package | `packageName` |
| Version | installed version of the package in the project (from Composer's `InstalledVersions`, not part of the audit output) |
| ID | `cve`, otherwise `advisoryId` (for example `PKSA-...`) |
| Description | `title`, as a link to `link` (opens in a new tab, `rel="noopener noreferrer"`). Only `http` and `https` addresses become links, anything else stays text. |
| Severity | `severity` (`critical`, `high`, `medium`, `low`), often empty: then "–" |
| Reported on | `reportedAt`, the date only |

Ten rows are visible, the rest with "Show all". At most 200 reports are saved, and only these fields (not `sources`, `affectedVersions` or the raw output). Packages that are no longer maintained come from `abandoned` (package, replacement).

## Nothing on the overview any more

The small traffic light `sulu_system` has left the overview, its content is the first tile of the tab ("Condition"). Saved arrangements of the overview that still name `sulu_system` do no harm: a key that no tile has any more is ignored. `disabled_widgets: ['sulu_system']` stays valid and does nothing. The tiles "Last check", "Database migrations" and "Failed messages" of the first versions of the tab are rows of the condition now, their saved places are ignored the same way.

Nothing secret is shown: no connection strings, keys or absolute paths, only versions, counts, package names and the relative folder names. A check that fails never shows the message of the error, only a short fixed text. Details go to the log.

## Permission

Under Settings → User Roles → Settings, the permission `sulu.settings.dashboard_system` ("view"). It is separate from `sulu.settings.dashboard`: a role needs both to see the tab. Without it there is no tab "System" (and no tab bar, if it would be the only one). No role has it at first, tick it for the administrator role only.

## The background check (cron)

`composer audit` asks the internet and counting the folders can take a while. Neither belongs in a request, so a console command does it and leaves the result for the tab:

```bash
php bin/adminconsole sulu:dashboard:system-check
```

Run it once a day, for example with this cron entry (adjust the path):

```
15 4 * * * cd /path/to/project && php bin/adminconsole sulu:dashboard:system-check >> var/log/system-check.log 2>&1
```

- Use `bin/adminconsole`: the tab reads the cache of the admin, and the command saves into it.
- A failed check ends with exit code 1 (Composer not found, no internet, too slow), a found vulnerability does not: the check itself worked.
- **Until the command has run, the condition says that nothing was checked yet** and the tab shows no vulnerabilities and no folder sizes. The result is kept in the cache and also contains the measurement before it (for the change). If the last run is older than 48 hours the tab warns that the cron job may have stopped (`sulu_dashboard.system.max_age`, in hours, for a weekly cron job for example 200).
- `cache:clear` also clears this result (depending on the cache setup of the project) and with it the measurement before: the display of the change starts again. Run the command at the end of a deployment, after `cache:clear`.
- Hosting without a crontab: use the cron function of the provider and let it start the command, or run it by hand now and then. A script that calls the command through a secret URL is only an idea, the bundle has no such route.
- Composer must be callable by the user of the cron job. If the command finds it under another name or path, set it: `sulu_dashboard.system.composer_binary: '/usr/local/bin/composer'` or the path to a `composer.phar`. Without a `HOME` directory (some cron environments) Composer needs `COMPOSER_HOME=/path/to/a/writable/folder` in front of the command. Without `composer.lock` in the project there is nothing to check.

## Check without a login

```bash
php bin/adminconsole sulu:dashboard:system-check
php bin/adminconsole sulu:dashboard:dump --user=admin --widget=sulu_system_status
php bin/adminconsole sulu:dashboard:dump --user=admin --group=system
```

The user must have the permission `sulu.settings.dashboard_system`, otherwise the tiles are missing from the output (that is the same as for the browser).

## Configuration

```yaml
sulu_dashboard:
    system:
        composer_binary: composer   # command in the PATH or path to a composer.phar
        max_age: 48                 # hours after which the last check counts as too old
        cache_limit: 1024           # MB: from this size of var/cache the tile "Storage" gives a hint (0 = off)
        log_limit: 512              # MB: the same for var/log (0 = off)
    disabled_widgets: ['sulu_system_environment', 'sulu_system_storage']   # switches tiles off (overview and tab)
```
