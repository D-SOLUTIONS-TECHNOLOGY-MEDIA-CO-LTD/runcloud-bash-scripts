# Phase 04 — Multi-server parallel runs + logs

**Priority:** P1 · **Status:** Not started

## Overview
Fan a single script out across many selected servers concurrently, one live output pane each, with per-server exit status and downloadable logs.

## Requirements
- Select N servers + one script → one **batch** with N child runs.
- Bounded concurrency (default 8 parallel, configurable) to avoid saturating the admin's uplink.
- Per-server pane: live output, status badge (running / ok / failed / unreachable), exit code.
- Batch summary: counts ok/failed/skipped (mirror the bash scripts' own summaries).
- Download full log per run and a combined batch log.

## Architecture
- `internal/runner/batch.go` — `RunBatch(ctx, servers, command, concurrency)` using a worker pool; reuse phase-03 `Run` per server.
- Persist logs to `~/.fleet-runner/logs/<batch-ts>/<host>.log` (durable, not `/tmp`).
- SSE multiplexed by `runID`; front-end renders a grid of panes.

## Files
- Create: `internal/runner/batch.go`, `internal/runner/batch_test.go`, `internal/web/templates/batch.html`, update `run.js`.

## Steps
1. Worker pool with semaphore; aggregate results.
2. Log persistence per run + batch.
3. Grid UI: one pane per server, collapsible, status-colored.
4. Batch summary bar + log download endpoints.

## Todo
- [ ] batch runner + bounded concurrency
- [ ] durable per-run/batch log files
- [ ] multi-pane SSE UI
- [ ] summary + downloads

## Success criteria
- Run e.g. `wp-malware-scan.sh` across all VN servers at once; each pane streams independently; batch summary shows correct ok/failed counts; logs downloadable.

## Risks
- One hung server stalling the batch — per-run timeout + cancel from phase 03 contains it.
- Output volume for fleet-wide scans — panes virtualize / cap in-memory tail, full log on disk.
