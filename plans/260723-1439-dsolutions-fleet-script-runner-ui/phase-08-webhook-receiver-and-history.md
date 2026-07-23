# Phase 08 — Webhook receiver + HMAC + history store

**Priority:** P0 · **Status:** Not started

## Overview
Let `server-metrics.sh` push metrics to fleet-runner (cron every 5m) instead of only being pulled on demand. Verify HMAC, upsert the latest snapshot per host, and append to a history log for trends.

## Requirements
- `POST /webhook/server-metrics` accepts the script's JSON body.
- **HMAC verify** matching `server-metrics.sh`: headers `X-Webhook-Signature` = HMAC-SHA256 of `{timestamp}.{body}`, `X-Webhook-Timestamp`. Reject on mismatch or stale timestamp (>5m skew). Timing-safe compare (`hmac.Equal`).
- Upsert the pushed server into the current snapshot keyed by hostname (replace that host's entry, keep others).
- Append each accepted push to a JSONL history file (`~/.fleet-runner/history.jsonl`) for later trend views.
- Shared secret via env `FLEET_WEBHOOK_SECRET` (same value cron uses).

## Architecture
- Reuse `metrics.Server` struct — the push body is the same JSON as `--print`, so it decodes directly (no field remapping; the mockup's n8n parser mismatch does not apply here).
- `internal/web/webhook.go` — handler: read body, verify HMAC, decode, `store.Upsert(server)`.
- `internal/metrics/store.go` — add `Upsert(Server)` (merge one host into the snapshot, set CollectedAt) and `AppendHistory(Server)`.
- Keep `/api/refresh` (pull) as manual fallback; both write through the same store.

## Files
- Create: `internal/web/webhook.go`, `internal/web/webhook_test.go`.
- Edit: `internal/metrics/store.go` (Upsert + history), `internal/web/server.go` (route).

## Steps
1. HMAC verify helper + unit test (valid, tampered, stale).
2. Upsert-by-hostname into snapshot; append JSONL history.
3. Route + secret config; document the cron line (reuse `server-metrics.sh` WEBHOOK_URL/WEBHOOK_SECRET).

## Todo
- [ ] HMAC verify + test
- [ ] store.Upsert + AppendHistory
- [ ] /webhook/server-metrics route
- [ ] cron/deploy doc

## Success criteria
- A cron `WEBHOOK_URL=…/webhook/server-metrics WEBHOOK_SECRET=… server-metrics.sh` updates that host's card within seconds; bad signatures are rejected; history grows one line per push.

## Security
- Timing-safe HMAC compare; reject stale timestamps (replay). Never log the secret or full signatures.
