# Phase 01 — Scaffold + fleet registry read

**Priority:** P0 · **Status:** Not started

## Overview
Stand up the Go single-binary web app skeleton and render the fleet server list from `~/.rc/rc.db`.

## Requirements
- Go module, single-binary build, `go run .` serves on `localhost:8091` (configurable via `-addr` / `PORT`).
- Read servers from `~/.rc/rc.db` (`SELECT hostname, ssh_port FROM servers ORDER BY hostname`). Path overridable via `-db` / `RC_DB`.
- Home page lists servers with region grouping (derive region from hostname prefix: `sg`, `jp`, `vn`) + a multi-select.
- Graceful errors if `rc.db` missing / `sqlite3` schema unexpected.

## Architecture
- `main.go` — flag parse, http server, graceful shutdown.
- `internal/fleet/registry.go` — `type Server{Hostname string; Port int; Region string}`; `Load(dbPath) ([]Server, error)` using `modernc.org/sqlite` (pure Go, no cgo).
- `internal/web/` — `html/template` pages, `embed.FS` for templates + static assets (single-binary friendly).
- Region derive: regex `^([a-z]+)\d` on hostname.

## Files
- Create: `go.mod`, `main.go`, `internal/fleet/registry.go`, `internal/web/server.go`, `internal/web/templates/index.html`, `internal/web/static/app.css`.

## Steps
1. `go mod init github.com/D-SOLUTIONS-.../fleet-script-runner`; add `modernc.org/sqlite`.
2. Implement `fleet.Load`; unit test against a temp sqlite db (mirror the test used for `wp-plugin-push.sh`).
3. HTTP server + index template rendering the server list.
4. `-addr`, `-db` flags; sane defaults (`:8091`, `$HOME/.rc/rc.db`).

## Todo
- [ ] go.mod + deps
- [ ] fleet.Load + test
- [ ] http server + index page
- [ ] server list render (region-grouped, multi-select)
- [ ] config flags + missing-db error UX

## Success criteria
- `go build` → single binary; running it lists all fleet servers from rc.db at `localhost:8091`.
- Missing/invalid rc.db shows a clear in-page error, no panic.

## Risks
- `modernc.org/sqlite` build size/time — acceptable for a single internal tool. Fallback: shell out to `sqlite3` CLI (already used by the bash scripts) if pure-Go proves heavy.
