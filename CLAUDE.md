# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`AGENTS.md` mirrors this file — keep the two in sync when editing either.

## What this is

**D-Solutions** fleet-ops scripts for operating **20 RunCloud-managed WordPress/Laravel servers** (SG, VN, JP regions). The core fleet scripts originate from [`codetot-web/runcloud-bash-scripts`](https://github.com/codetot-web/runcloud-bash-scripts) (by @khoipro, used with attribution); the `wp-malware-*` tooling is original D-Solutions work. Replace example hosts (`sgX.example.com`) and paths (`/Users/you/...`) with the real fleet values. Scripts are authored/tested on macOS but run as `root` on Ubuntu 20/22/24. Each server has this repo cloned at `/root/runcloud-bash-scripts/`. (A team web UI to run these scripts is a planned D-Solutions project — not built yet.)

## Fleet registry (source of truth)

Server hostnames and SSH ports live in a local SQLite DB, **not** in this repo:
```bash
sqlite3 ~/.rc/rc.db "SELECT hostname, ssh_port FROM servers ORDER BY hostname;"
```
SSH ports vary per server (22 or 2018). Always look up the port from `rc.db` — never assume. `~/.rc/config.yaml` is the human-readable fleet config.

## Slash commands (encode the real workflows)

- `/deploy` — `git push`, then `git pull --ff-only` the scripts to all 20 servers in parallel (fleet read from `~/.rc/rc.db`).
- `/fleet-status` — SSH to every server for its current git commit.
- `/test-script <script> <server> <args>` — `bash -n`, scp to one server, run, clean up. Use this before any fleet deploy.

## Script conventions

Every script follows the same anatomy (see `wp-vuln-check.sh` as the reference):
- `#!/bin/bash` + `set -euo pipefail`
- A header comment block (usage/options) that `--help`/`-h` prints back via `awk`
- Color helpers: `info()`, `success()`, `warn()`, `error()` (error → stderr)
- Arg parsing: `--site=NAME` (searches `/home/*/webapps/`) **or** `--path=PATH` (full path); most action scripts also take `--dry-run`
- Site owner detection: `stat -c '%U'` (Linux) with `stat -f '%Su'` (BSD/macOS) fallback — scripts must work on both platforms
- wp-cli: search candidate paths (`/usr/local/bin/wp`, `/usr/bin/wp`, RunCloud agent) — PATH is unreliable under cron
- Run wp-cli **as the site owner**: `sudo -u "$SITE_OWNER" "$WP_CLI" --path="$SITE_PATH" ...`

Migration scripts (`wp-migration.sh`, `wp-local-to-production.sh`, `laravel-migration.sh`) are the exception: run from the **source** (a server or the Mac) and SSH into the destination; support `--setup-ssh` to seed keys first.

## Testing & deploy flow

1. `bash -n script.sh` — syntax check locally.
2. Test on one server (via `/test-script` or manually):
   ```bash
   scp -P 2018 script.sh root@sg9.example.com:/root/test.sh
   ssh -p 2018 root@sg9.example.com "bash /root/test.sh --site=vinhhoan"
   ```
3. Commit, bump `VERSION` + `CHANGELOG.md` for notable changes, then `/deploy`.

On servers, `git checkout -- .` runs before `git pull` because `chmod +x *.sh` creates filemode diffs that would otherwise block a fast-forward pull.

## Common pitfalls

- Never hardcode `/home/runcloud/` — apps are multi-user under `/home/*/webapps/`. Glob it or accept `--path=`.
- wp-cli not in PATH during cron — always search candidate paths.
- Frozen sites: `wp-freeze.sh` locks files read-only + drops a `code-freeze.php` mu-plugin; scripts that mutate sites (`wp-git-cleanup.sh`, updates) skip frozen sites.
- Reference PHP binary matters: `wp-health-check.sh` runs wp-cli through the app's **FPM/LSPHP** binary, not system CLI PHP, to avoid false "mysqli missing" errors.

## Non-script assets

- `sop-templates/` — standalone HTML runbooks + Typesense helper scripts (not part of the fleet deploy).
- `.gstack/` — gitignored local scratch.
