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
To build this project into a standard installable `.pkg` package via the FreeBSD ports infrastructure later:
1. Nest this entire repository structure straight into an official `opnsense/plugins` checkout at `sysutils/os-git-backup/`.
2. This project is structurally designed to **completely replace and upgrade** the legacy plugin. Ensure the root `Makefile` sets `PLUGIN_NAME=os-git-backup` and bumps `PLUGIN_VERSION=1.2` to cleanly overwrite the installed `1.1_5` dependency tree safely.
3. Complete the `pkg-plist` manifest by appending repo-relative deployment file tracking entries (omitting the `src/` prefix) to satisfy the standard build layout validation constraints.
