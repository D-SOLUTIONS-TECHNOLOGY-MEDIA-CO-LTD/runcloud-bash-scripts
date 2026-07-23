# Phase 06 — Deferred: auth + internal-server deploy

**Priority:** P2 (deferred) · **Status:** Deferred

Only pursue if the runner moves off the admin's Mac onto a shared internal host so multiple team members use it.

## Scope (when triggered)
- **Auth:** simple team login (session cookie + a small user table, or reverse-proxy SSO). No public exposure.
- **SSH key handling:** dedicated deploy key on the host with root access to the fleet; keep it off developer laptops.
- **Deploy:** single binary + `systemd` unit (or a small Docker image), bound to an internal interface / behind Tailscale.
- **Audit:** attribute each run to a user in the history index.
- **Hardening:** CSRF on POST, per-user rate limits on destructive actions.

## Why deferred
MVP (phases 01–05) runs on the admin's Mac at localhost with existing `~/.ssh` + `~/.rc` — no new attack surface. Adding auth/hosting is only worth it once the access pattern is genuinely multi-user.

## Open questions
- Which host? (internal VPS vs a workstation always-on)
- SSO available (Google Workspace) or roll a minimal login?
- Do we need per-user SSH identities, or one shared fleet key with app-level attribution?
