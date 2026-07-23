# Phase 03 — SSH exec + live output streaming (SSE)

**Priority:** P0 · **Status:** Not started

## Overview
Execute a built command on one server over SSH and stream combined stdout/stderr live to the browser.

## Requirements
- Shell out to system `ssh -p <port> -o ConnectTimeout=10 root@<host> "<command>"` (reuses `~/.ssh`).
- Stream output to the browser in real time via **SSE** (`text/event-stream`).
- Capture exit code; mark run finished; surface connection failures distinctly from script failures.
- Per-run cancel (kill the ssh child process).

## Architecture
- `internal/runner/runner.go` — `Run(ctx, server, command) (<-chan Line, <-chan Result)`; uses `exec.CommandContext`, merges stdout+stderr via `cmd.StdoutPipe`/`StderrPipe` + `bufio.Scanner`, tags each line with stream.
- `internal/web` `/api/run` (POST → returns runID) + `/api/run/{id}/stream` (SSE).
- In-memory run registry (`map[runID]*Run` + mutex); output buffered so late-connecting SSE clients get backlog.
- `ConnectTimeout` + a hard wall-clock cap per run (configurable, default 10m) to avoid zombie runs.

## Files
- Create: `internal/runner/runner.go`, `internal/runner/runner_test.go`, `internal/web/stream.go`, `internal/web/static/run.js`.

## Steps
1. Runner: exec ssh, stream lines over a channel, return exit result.
2. SSE endpoint flushing lines; heartbeat comments to keep connection alive.
3. Front-end: open `EventSource`, append lines, show spinner → exit badge.
4. Cancel button → `ctx` cancel → ssh killed.

## Todo
- [ ] runner.Run with merged stream + exit code
- [ ] run registry + backlog buffer
- [ ] SSE endpoint + heartbeat
- [ ] front-end EventSource renderer + cancel
- [ ] connection-fail vs script-fail distinction

## Success criteria
- Running a read-only script (e.g. `wp-health-check.sh --site=X`) on one server streams output live and ends with the real exit code.

## Risks
- SSE buffering behind proxies — N/A on localhost. Keep `Flush()` after each event.
- Long-running scripts (migrations) — the wall-clock cap + cancel mitigate; migrations may warrant a higher per-script timeout in the catalog.
