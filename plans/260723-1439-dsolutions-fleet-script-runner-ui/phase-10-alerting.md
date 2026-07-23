# Phase 10 — Alerting (Zalo OA on thresholds)

**Priority:** P2 (optional) · **Status:** Optional

## Overview
On each accepted metrics push, fire an alert when a server crosses a threshold, so problems surface 24/7 without anyone watching the dashboard. Mirrors the mockup's n8n → Zalo OA step, done in Go.

## Requirements
- Evaluate thresholds per push (defaults: `cpu ≥ 90`, `disk ≥ 85`, `ram ≥ 90`; configurable).
- Send to Zalo OA (or generic webhook / Telegram) with hostname + which metric tripped.
- **Debounce:** don't re-alert the same host+metric while still tripped; alert again only after it recovers and re-trips (avoid spam every 5m).
- Config via env: `ALERT_ZALO_TOKEN`, thresholds; alerting off if token unset.

## Architecture
- `internal/alert/alert.go` — `Evaluate(prev, cur Server) []Alert`; `Notifier` interface with a Zalo implementation (+ a no-op/log one for dev).
- Hook into the webhook handler (phase 08) after a successful upsert, comparing against the previous snapshot for that host (debounce state).

## Files
- Create: `internal/alert/alert.go`, `internal/alert/alert_test.go`.
- Edit: `internal/web/webhook.go` (invoke evaluator post-upsert).

## Todo
- [ ] threshold evaluate + debounce state
- [ ] Zalo notifier (+ log notifier for dev)
- [ ] wire into webhook path
- [ ] config + off-by-default when unconfigured

## Success criteria
- A server crossing a threshold triggers exactly one Zalo message, no repeats until it recovers; disabled cleanly when no token is set.

## Open questions
- Zalo OA vs Telegram vs email for the team's on-call channel?
- Alert only on push, or also on "server stopped pushing for N minutes" (staleness = likely down)?
