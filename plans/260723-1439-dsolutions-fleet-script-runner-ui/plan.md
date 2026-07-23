# D-Solutions Fleet Dashboard + Script Runner — Plan

One Go service ("fleet-runner") that monitors and operates the RunCloud WordPress fleet. Replaces both Khôi's `runcloud-go` dashboard **and** the n8n+Airtable mockup — collapsing them into a single self-hosted binary.

## Architecture (locked: Go all-in-one)
Chosen over the n8n+Airtable mockup to cut moving parts and keep customer data off external SaaS. The Go service does everything n8n+Airtable did:

```
[VPS ×N]  server-metrics.sh                on-demand: fleet-runner
  cron */5  --push (HMAC)                    SSHes `--print` in parallel
        \                                   /
         v                                 v
        [ fleet-runner (Go, self-hosted) ]
          POST /webhook/server-metrics  (HMAC verify → upsert snapshot + append history)
          dashboard UI  (servers / sites / scripts / setup)
          POST /api/run  (whitelist + safety → SSH exec, live SSE output)
          optional: threshold alert → Zalo OA
          store: local (latest snapshot + JSONL history), no external DB
```

- **Push-first, pull-fallback:** cron pushes every 5m (fresh data + passive 24/7 alerting); the on-demand "Refresh" (parallel SSH) stays as a manual fallback.
- **Parser reuse:** `server-metrics.sh` pushes the *same* JSON as `--print`; the existing `metrics.Server` struct decodes it directly. (The mockup's n8n "flatten" node read `body.cpu`/`body.load['1m']`/`body.memory.*` — fields the real script never emits, so it would zero everything. Avoided entirely here.)
- **Deps:** stdlib only + `sqlite3` CLI for rc.db. SSH via system `ssh`. Builds offline.
- **Hosting:** for team/24-7 use, run the binary on a small internal host behind Cloudflare Access or Tailscale (see phase 06, now in-scope, not deferred).

## Surfaces
- **A — Monitoring** (Servers + Applications + per-server drill-down). *Pull path built; add push receiver + history.*
- **B — Script Runner** (select site → whitelisted script → live output + safety guardrails).

## UI
Adopt the mockup's React layout (4 tabs: servers w/ expandable per-site rows, all-sites sorted by updates, script runner + terminal, setup). **Open sub-decision:** serve a pre-built React bundle from Go (matches mockup, adds a JS build step) vs. port the mockup's UX into the current stdlib server-rendered templates (no build step). Default lean: server-rendered, adopting the mockup's layout/UX — preserves the offline, dependency-free build.

## Phases
| # | Phase | Status |
|---|-------|--------|
| 01 | [Scaffold + fleet registry read](phase-01-scaffold-and-fleet-registry.md) | **Done** |
| 07 | [Inventory dashboard (pull)](phase-07-inventory-monitoring-dashboard.md) | **Done (MVP)** |
| 08 | [Webhook receiver + HMAC + history store](phase-08-webhook-receiver-and-history.md) | **Done** |
| 09 | [UI: adopt mockup layout (drill-down, sites, setup)](phase-09-ui-adopt-mockup-layout.md) | Not started |
| 02 | [Script catalog manifest + run form](phase-02-script-catalog-and-run-form.md) | Not started |
| 03 | [SSH exec + live output streaming (SSE)](phase-03-ssh-exec-and-live-streaming.md) | Not started |
| 04 | [Multi-server parallel runs + logs](phase-04-multi-server-runs-and-logs.md) | Not started |
| 05 | [Safety UX + run history](phase-05-safety-ux-and-history.md) | Not started |
| 10 | [Alerting (Zalo OA on thresholds)](phase-10-alerting.md) | Optional |
| 06 | [Auth + host behind Cloudflare Access / Tailscale](phase-06-deferred-auth-and-deploy.md) | In scope (multi-user) |

Suggested order: **08 → 09** (finish monitoring parity with push + nicer UI), then **02–05** (script runner), then **06** (host for team) and **10** (alerts).

## Done so far (repo: local `fleet-script-runner`, committed, not pushed)
Go single-binary; rc.db read; parallel SSH collect of `server-metrics.sh --print`; snapshot cache; Servers + Applications views (search/sort/filter, update highlights, frozen badge); tests for registry + parse; verified render via screenshots.

## Non-goals
- No Airtable / n8n. No external DB (local JSON/JSONL; revisit only if history volume demands it).
- No arbitrary command execution — script runner is whitelist-only.
