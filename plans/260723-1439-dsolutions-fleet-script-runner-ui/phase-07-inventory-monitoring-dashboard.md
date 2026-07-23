# Phase 07 — Inventory / monitoring dashboard (Surface A)

**Priority:** P0 (parity with Khôi's `runcloud-go`) · **Status:** Not started

## Overview
Recreate the Servers + Applications monitoring views the team uses today, fed by `server-metrics.sh` — which already emits exactly the needed data.

## Data source (no new collection code)
`server-metrics.sh --print` returns per-server JSON. Confirmed shape:
- **Server:** `hostname`, `ip_address`, `cpu_percent`, `cpu_cores`, `load_1m/5m/15m`, `ram_total_mb`, `ram_used_mb`, `ram_percent`, `disk_total_mb`, `disk_used_mb`, `disk_percent`, `uptime_seconds`, `reported_at`, `web_apps[]`.
- **web_apps[]:** `username`, `webapp_name`, `path`, `disk_used_mb`, `wp_version`, `wp_core_update`, `wp_plugin_updates`, `wp_theme_updates`, php/git/freeze fields.

Maps 1:1 to the dashboard columns: APP NAME=`webapp_name`, SERVER=`hostname`, TYPE=WP (has `wp_version`), WP VERSION=`wp_version`, CORE=`wp_core_update`, PLUGINS=`wp_plugin_updates`, THEMES=`wp_theme_updates`, STATUS=live/frozen (freeze field), DISK=`disk_used_mb`.

## Requirements
- **Servers view:** list from rc.db + live CPU/RAM/disk/load/uptime + app count per server.
- **Applications view:** flat table across the fleet — searchable, sortable by any column, filterable by type/status/server; highlight rows with pending core/plugin/theme updates (like the screenshot's colored counts).
- **Refresh model:** on-demand ("Refresh" runs `server-metrics.sh --print` across the fleet in parallel) + a cached snapshot so the page loads instantly. Cache TTL configurable; show `reported_at` age.
- **Frozen** apps badged distinctly (freeze field from metrics).

## Architecture
- Reuse phase-03 runner to exec `bash /root/runcloud-bash-scripts/server-metrics.sh --print` per server (parallel, bounded).
- `internal/metrics/collect.go` — run across fleet, parse JSON, merge into `type Snapshot{Servers[]; Apps[]}`.
- Cache snapshot at `~/.fleet-runner/metrics-snapshot.json`; `internal/metrics/store.go` load/save + age.
- `internal/web` renders `/` (servers) and `/apps` (applications table). Client-side sort/filter/search over the JSON (no server round-trips).

## Files
- Create: `internal/metrics/collect.go`, `internal/metrics/store.go`, `internal/metrics/collect_test.go`, `internal/web/templates/servers.html`, `internal/web/templates/apps.html`, `internal/web/static/table.js`.

## Steps
1. Parse a single server's `--print` JSON into structs (test against a captured sample).
2. Fleet collect (parallel) → merged snapshot + disk cache.
3. Servers page + Applications table with client-side search/sort/filter + update-count highlighting.
4. "Refresh" action + snapshot-age indicator.

## Todo
- [ ] JSON parse + structs + sample-based test
- [ ] parallel fleet collect + snapshot cache
- [ ] servers view
- [ ] applications table (search/sort/filter, update highlight, frozen badge)
- [ ] refresh + age indicator

## Success criteria
- Landing page shows all fleet servers with live resource stats and an Applications table matching Khôi's dashboard columns, sortable/searchable, updates highlighted — data sourced entirely from `server-metrics.sh`.

## Risks
- Fleet-wide `--print` is slow (wp-cli per app, 30s timeouts). Mitigate: run in parallel, cache the snapshot, refresh on demand rather than per page load. A future optimization is a cron pushing metrics to a local store (the script already supports a webhook mode).
