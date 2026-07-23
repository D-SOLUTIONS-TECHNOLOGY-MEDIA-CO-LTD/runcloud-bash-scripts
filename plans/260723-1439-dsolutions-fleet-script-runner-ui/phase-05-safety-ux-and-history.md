# Phase 05 — Safety UX + run history

**Priority:** P1 · **Status:** Not started

## Overview
Turn the `safety` class into concrete guardrails, and keep a browsable history of past runs.

## Requirements
- **read-only:** run immediately, no friction.
- **mutating:** `--dry-run` toggled ON by default where the script supports it; explicit "Run for real" step.
- **destructive/high-impact:** typed confirmation modal naming the target servers + script before execution (e.g. `wp-freeze`, `wp-malware-quarantine`, `tweak-mycnf` apply, `change-ssh-port`, migrations).
- History: list of past batches (ts, script, servers, ok/failed, link to logs). Backed by the on-disk logs from phase 04 + a small index file.
- Clear labeling of safety class + a one-line "what this does / regenerates automatically?" note per script.

## Architecture
- `internal/catalog` already carries `safety` + a `supportsDryRun` flag per script → drives UI.
- `internal/history/history.go` — append-only JSONL index at `~/.fleet-runner/history.jsonl`; list + filter.
- Confirmation enforced server-side too (not just JS) — destructive runs require a `confirm=<token>` param.

## Files
- Create: `internal/history/history.go`, `internal/web/templates/history.html`; update run/batch handlers + templates.

## Steps
1. Wire safety class → form defaults + confirm gate (client + server enforced).
2. Append run records to history index; render history page with log links.
3. Per-script help note surfaced from catalog description.

## Todo
- [ ] dry-run default + "run for real" for mutating
- [ ] typed confirm modal for destructive (server-enforced)
- [ ] history JSONL + page
- [ ] safety labels + help notes in UI

## Success criteria
- A destructive script cannot execute without explicit confirmation; mutating scripts default to dry-run; every run is recorded and its log retrievable later.

## Security considerations
- Even localhost/no-auth, server-side confirm gate prevents accidental one-click destructive fan-out. Log who/when is implicit (single admin) but timestamped for audit.
