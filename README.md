# OPNsense Git Backup MVC Extension (Upstream Payload)

This repository houses a self-contained, upstream-ready MVC refactoring of the official OPNsense `os-git-backup` plugin. It modernizes the administration interface by migrating configurations out of the core legacy `diag_backup.php` view into a clean, native Phalcon MVC dashboard.

## Key Enhancements Delighted
- **True Local-Only Mode:** Adds an explicit option to retain configuration history locally on disk via git version control without forcing an upstream remote network push.
- **Data Lifecycle Safeguards:** Implements a client-side form serialization dirty-checker that locks inputs and focus traversal (`tabindex="-1"`) dynamically based on context, preventing accidental text/token data-erasure.
- **Non-Destructive Testing:** Adds a side-effect-free asynchronous connection validator using a sandboxed `git ls-remote` check to verify credentials before committing changes.
- **Destructive Warning Core:** Integrates a real-time contextual Bootstrap alert banner that flags the risks of enabling a Force Push (`--force`) operation.

## Architecture & Integration Layout
When integrating this source payload into an active clone of the official `opnsense/plugins` monorepo, files must be overlaid into `sysutils/os-git-backup/` with the following architectural mappings:

1. **Model & Scheme Properties:**
   - Move `src/opnsense/mvc/app/models/OPNsense/Backup/GitSettings.xml` into the matching shared space to map the explicit `<dest_type>` selector node.
   - Retain `src/opnsense/mvc/app/models/OPNsense/Backup/GitSettings.php` to run custom `performValidation()` hooks that bypass empty URL/User parameters during Local-only configuration commitments.

2. **API & View Routers:**
   - Move the contents of `src/opnsense/mvc/app/controllers/OPNsense/GitBackup/` into the ports tree under its unique plugin-owned module namespace.
   - The view layout maps via standard Volt helpers, routing asynchronous requests through the canonical snake_case API controller mapping (`/test_connection`).

## Upstream Integration & Compilation Notes
Verified against a shallow clone of `opnsense/plugins` and its `Mk/plugins.mk`:

1. Stage this repository into `sysutils/git-backup/` — the directory carries the *source* name. The package name `os-git-backup` is produced by `PLUGIN_PREFIX` (`os-`), so `PLUGIN_NAME` must stay `git-backup`; setting it to `os-git-backup` yields `os-os-git-backup` plus a second, wrong `opnsense/version/os-git-backup` descriptor.
2. `PLUGIN_VERSION` is `1.2`, and the plugin declares `PLUGIN_DEPENDS=git` and `PLUGIN_TIER=2`, matching the upstream plugin it replaces.
3. **Do not ship `pkg-plist` or `opnsense/version/git-backup`.** `make plist` derives the manifest from `src/` (absolute `/usr/local/...` entries) and `make install` writes the version descriptor from the `PLUGIN_*` values. A committed plist is ignored by the framework, and a committed version descriptor duplicates the plist entry and freezes a stale `product_hash`.
4. `make package` in a development clone produces `os-git-backup-devel-1.2.pkg` (`Mk/devel.mk` forces development mode on the development branch) with `product_id: os-git-backup-devel` and `product_conflicts: os-git-backup`; the same tree on a release branch yields `os-git-backup-1.2`.
