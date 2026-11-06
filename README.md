# os-git-backup-mvc

MVC front end and connectivity tester for the OPNsense git configuration backup provider
(`os-git-backup`), extracted from a hardened testbed so it can be maintained and upstreamed
independently.

## What it adds

- a dedicated page **System → Configuration → Git Backup** (`/ui/gitbackup`) built on the native
  OPNsense MVC/Volt stack instead of the legacy `diag_backup.php` provider block;
- an explicit **Destination Type** (`local` / `remote`) so a local history can be kept without pushing;
- a **non-destructive `git ls-remote` connectivity test** (`/api/gitbackup/settings/test_connection`)
  that validates credentials and the target branch without committing, pushing or writing config;
- field lockout in the UI: remote fields are disabled and dimmed while the provider is off or set to
  local, and a destructive-action warning appears when Force Push is enabled in remote mode;
- a migration notice replacing the legacy form fields (see caveat 3).

## Layout

    src/opnsense/mvc/app/models/OPNsense/Backup/GitSettings.xml      model, mounted at //system/backup/git
    src/opnsense/mvc/app/models/OPNsense/Backup/GitSettings.php      model class, local-mode validation
    src/opnsense/mvc/app/models/OPNsense/GitBackup/Menu/Menu.xml     sidebar entry
    src/opnsense/mvc/app/models/OPNsense/GitBackup/ACL/ACL.xml       page/API privileges
    src/opnsense/mvc/app/controllers/OPNsense/GitBackup/…            page + API controllers, form definition
    src/opnsense/mvc/app/views/OPNsense/GitBackup/index.volt         page view
    src/opnsense/mvc/app/library/OPNsense/Backup/Git.php             provider, backup(), testConnection()
    src/etc/rc.syshook.d/config/10-git-backup                        local commit on config change
    src/opnsense/version/git-backup                                  package version marker

## Caveats

1. `GitSettings.xml`/`.php` deliberately stay in the **`OPNsense\Backup`** namespace: the autoloader maps
   namespaces to paths, the model is mounted at `//system/backup/git`, and `Backup\Git` instantiates it.
   Moving them to `OPNsense/GitBackup/` would break the provider, the legacy page and the cron push.
2. The form definition must live in `controllers/OPNsense/GitBackup/forms/settings.xml`; `getForm()` reads
   it relative to the controller, so `app/forms/OPNsense/GitBackup/settings.xml` would not be found.
3. `Git::getConfigurationFields()` returns a migration notice that renders as a full-width alert on the
   legacy backups page. That notice closes and reopens table cells, so it depends on the current legacy
   renderer in `src/www/diag_backup.php`.
4. Packaging metadata (`Makefile`, `pkg-descr`, `+PLIST`) is not included; this tree is the source
   payload, not a buildable `opnsense/plugins` submission.
5. Nothing here is translation-complete: strings use `gettext()`/`lang._()`, no `.pot` was generated.

## Behaviour notes

- `dest_type=local` commits to `/conf/backup/git` and returns before any upstream setup, so no push is
  attempted. `dest_type=remote` keeps the original push path with optional force push.
- A save from the new page stores settings but does not trigger a push; the local commit happens through
  the `config_changed` syshook and the push through the `remote.backup` cron action.
- The connectivity test sanitises the credential-bearing URL out of the output before it is returned.
