# Phase 09 — UI: adopt the mockup layout

**Priority:** P1 · **Status:** Not started

## Overview
Bring the mockup's UX into the dashboard: a single Servers table with **expandable per-site rows**, an **All Sites** tab sorted by pending updates, a header with live stat chips (servers / sites / updates pending / critical), and a Setup tab.

## Reference
Mockup: `~/Downloads/ds-vps-dashboard/ds-vps-dashboard.jsx` — layout, color scale (cpu/ram/disk thresholds green/amber/red), MiniBar, update Badges, expandable rows, terminal panel.

## Requirements
- **Header:** logo + live chips: `N servers`, `M sites`, `K updates pending`, `C critical` (cpu≥80 / ram≥85 / disk≥85), data freshness.
- **Servers tab:** one row/server (hostname+region dot, CPU/RAM/Disk with mini-bars + thresholds, load, uptime, site count, update badge). Click → expand to per-site sub-table (site, WP ver, disk, core/plugin/theme badges, "run script" button → prefills runner).
- **All Sites tab:** every webapp across the fleet, sortable, default sort by total updates desc; "run script" per row.
- **Setup tab:** static install/cron/webhook guide (use the real flat JSON payload + the correct `/webhook/server-metrics` cron, not the mockup's n8n version).

## Sub-decision (carry from plan.md)
- **A) Server-rendered (default):** port the mockup's layout into `html/template` + vanilla JS. No build step; keeps the binary self-contained/offline. Reuse existing `table.js` sort/filter.
- **B) React bundle:** build the mockup's `.jsx` (Vite) → embed the static `dist` in Go. Closest to the mockup, adds a JS toolchain.
Pick before starting; A recommended unless the team wants the richer React interactions.

## Files (option A)
- Edit: `internal/web/templates/servers.html` (expandable rows + header chips), add `sites.html`, `setup.html`; extend `static/app.css`, `static/table.js`.

## Todo
- [ ] header live chips (servers/sites/updates/critical)
- [ ] servers table w/ mini-bars + expandable per-site rows
- [ ] all-sites tab (sorted by updates) + run-script prefill
- [ ] setup tab (correct payload + cron)

## Success criteria
- Dashboard visually matches the mockup's information density; clicking a server reveals its sites; "run script" jumps to the runner with site prefilled.
