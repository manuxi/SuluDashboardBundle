# The tile "System"

For administrators: versions, environment and the things that should not be forgotten. The tile only appears for users who have its own permission, so editors never see it.

## What it shows

| Row | Meaning |
|---|---|
| PHP, Symfony, Sulu | installed versions |
| Environment, debug mode | `prod`, `dev` ... and whether debug is on. **Debug mode on in `prod` is shown as a red warning at the top**, because it can reveal internal details to visitors (`APP_DEBUG=0`, then clear the cache). |
| Security check | result of `composer audit`: either "no known vulnerabilities" or a red row with the number of vulnerabilities, below it the affected packages with title and CVE (ten at most) |
| Database migrations | only with `doctrine/doctrine-migrations-bundle`: "up to date" or a warning with the number of open migrations (read only, nothing is run) |
| Failed messages | only when Symfony Messenger has a `failure_transport`: "none" or a warning with the number (read only, nothing is retried or deleted) |
| `public/uploads`, `var` | space used by these folders |
| Last check | age of the background check, see below |

Nothing secret is shown: no connection strings, keys or absolute paths, only versions, counts, package names and the relative folder names. A check that fails never shows the message of the error, only a short fixed text. Details go to the log.

## Permission

Under Settings → User Roles → Settings, the permission `sulu.settings.dashboard_system` ("view"). It is separate from `sulu.settings.dashboard`: a role needs both to see the tile. No role has it at first, tick it for the administrator role only.

## The background check (cron)

`composer audit` asks the internet and counting the folders can take a while. Neither belongs in a request, so a console command does it and leaves the result for the tile:

```bash
php bin/adminconsole sulu:dashboard:system-check
```

Run it once a day, for example with this cron entry (adjust the path):

```
15 4 * * * cd /path/to/project && php bin/adminconsole sulu:dashboard:system-check >> var/log/system-check.log 2>&1
```

- Use `bin/adminconsole`: the tile reads the cache of the admin, and the command saves into it.
- A failed check ends with exit code 1 (Composer not found, no internet, too slow), a found vulnerability does not: the check itself worked.
- **Until the command has run, the tile says "The background check has not run yet"** and shows no security check and no folder sizes. If the last run is older than 48 hours the tile warns that the cron job may have stopped (`sulu_dashboard.system.max_age`, in hours, for a weekly cron job for example 200).
- `cache:clear` also clears this result. Run the command at the end of a deployment, after `cache:clear`.
- Hosting without a crontab: use the cron function of the provider and let it start the command, or run it by hand now and then. A script that calls the command through a secret URL is only an idea, the bundle has no such route.
- Composer must be callable by the user of the cron job. If the command finds it under another name or path, set it: `sulu_dashboard.system.composer_binary: '/usr/local/bin/composer'` or the path to a `composer.phar`. Without a `HOME` directory (some cron environments) Composer needs `COMPOSER_HOME=/path/to/a/writable/folder` in front of the command. Without `composer.lock` in the project there is nothing to check.

## Check without a login

```bash
php bin/adminconsole sulu:dashboard:system-check
php bin/adminconsole sulu:dashboard:dump --user=admin --widget=sulu_system
```

The user must have the permission `sulu.settings.dashboard_system`, otherwise the tile is missing from the output (that is the same as for the browser).

## Configuration

```yaml
sulu_dashboard:
    system:
        composer_binary: composer   # command in the PATH or path to a composer.phar
        max_age: 48                 # hours after which the last check counts as too old
    disabled_widgets: ['sulu_system']   # switches the tile off completely
```
