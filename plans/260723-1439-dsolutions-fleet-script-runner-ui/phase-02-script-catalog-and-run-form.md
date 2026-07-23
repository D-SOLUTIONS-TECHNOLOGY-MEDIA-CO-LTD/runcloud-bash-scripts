# Phase 02 — Script catalog manifest + run form

**Priority:** P0 · **Status:** Not started

## Overview
Define a curated catalog describing each fleet script, its flags, and its safety class; render a per-script run form.

## Requirements
- Catalog is code/embedded data (not user-editable in MVP). One entry per exposed script.
- Each entry: `name`, `script` (filename), `description`, `category`, `safety` (`read-only|mutating|destructive`), `args[]`.
- Arg types: `string` (e.g. `--site=`), `bool` (e.g. `--dry-run`), `enum` (e.g. `--action=freeze|unfreeze|status`), `choice`.
- Form renders inputs per arg; `--site` offered as free text (site autodiscovery is a later nicety).

## Architecture
- `internal/catalog/catalog.go` — `Script`, `Arg` structs + the manifest as a Go slice (or embedded `catalog.yaml` via `embed`).
- `internal/web` renders `/run/{script}` form from the catalog entry.
- Command builder: `catalog.BuildCommand(script, values) (string, error)` → `bash /root/runcloud-bash-scripts/<file> <flags>`, with strict validation (whitelist arg names, escape values).

## Initial catalog (safety class → run UX in phase 05)
- **read-only:** `wp-malware-scan.sh`, `wp-health-check.sh`, `wp-vuln-check.sh`, `wp-ownership-audit.sh` (no `--fix`), `wp-plugin-list.sh`, `wp-git-cleanup.sh --action=scan`, `server-metrics.sh --print`, `tweak-mycnf.sh --status`.
- **mutating:** `wp-update.sh`, `wp-plugin-push.sh`, `wp-git-cleanup.sh --action=cleanup`, `chown-site.sh`, `fix-permission-site.sh`, `wp-ownership-audit.sh --fix`, `cleanup-disk.sh`, `install-ioncube.sh`, `update-nodejs.sh`.
- **destructive / high-impact (confirm):** `wp-freeze.sh` (freeze/unfreeze), `wp-malware-quarantine.sh`, `tweak-mycnf.sh` (apply → restarts MariaDB), `change-ssh-port.sh`, `wp-migration.sh`, `laravel-migration.sh`.

## Files
- Create: `internal/catalog/catalog.go`, `internal/catalog/catalog_test.go`, `internal/web/templates/run.html`.

## Steps
1. Define structs + manifest for the read-only set first (safe to demo).
2. `BuildCommand` with arg whitelist + shell-safe quoting; unit test malicious input rejcontainment.
3. Render run form; wire `--dry-run` default-on for mutating/destructive.

## Todo
- [ ] catalog structs + manifest (read-only set)
- [ ] BuildCommand + validation/escaping tests
- [ ] run form template
- [ ] extend manifest to mutating + destructive sets

## Success criteria
- Selecting a script shows a correct form; `BuildCommand` emits the exact CLI a human would type, and rejects unknown args / injection attempts.

## Security considerations
- Never interpolate raw user input into the SSH command — whitelist arg keys, validate values (site name `^[a-zA-Z0-9._-]+$`), quote with care. This is the primary attack surface.
