# Changelog

All notable changes to `runcloud-bash-scripts` will be documented in this file.

## [1.5.0] — 2026-07-24

### Added

- **server-metrics.sh**: report the host's `ssh_port` in the payload (detected via
  `sshd -T`, falling back to `sshd_config`/`sshd_config.d`, then 22). Lets the fleet
  dashboard reach each server on its real SSH port — mixed 22/2018 fleets no longer
  need a local server registry to run scripts.

## [1.4.3] — 2026-07-23

### Fixed

- **wp-malware-scan.sh Family 3b**: tighten false-positive filter.
  - Previously matched $OBF | $COOKIE (OR) — flagged ManageWP Worker mu-plugin,
    Elementor Safe Mode, legit theme child functions.php, and any theme file that
    reads $_COOKIE routinely. 22 false positives on galle rescan.
  - Now requires BOTH $OBF (eval/base64_decode/hex2bin/…) AND $COOKIE in the
    same file — attackers combine them, legit code rarely does.
  - Skips mu-plugins/ entirely (0-worker.php trivially trips single-token match).
  - Skips WP drop-ins advanced-cache.php, object-cache.php, db.php.


## [1.4.2] — 2026-07-23

### Fixed

- **wp-malware-scan.sh**: add Family 3b — webshell drop detection at wp-content/ and
  wp-content/themes/ roots. Scanner previously only checked uploads/ for eval-cookie
  webshells; attackers on the FOPO/eval-obfuscator family also drop identical payloads at
  themes/wp-configs.php (same md5 as their uploads/wp-configs.php copy). Filters out
  standard WP theme filenames (functions.php, index.php, header.php…) and only flags
  suspicious content matches. Discovered while cleaning galle/tahamin (D-Solutions client).


## [1.4.1] — 2026-07-23

### Fixed

- **wp-malware-scan.sh**: raise oversized-README threshold from 150KB to 500KB, and skip files
  starting with the WordPress plugin readme header (`=== Plugin Name ===`) at any size —
  legit large plugins like WP Fusion ship 400KB readme.txt without being malware. The
  oversized-README rule is a secondary check; the primary signal for fake-plugin loaders
  is the odd-plugin shape (rule 1b: few files, no readme).


## [1.4.0] — 2026-07-23

### Added

- **wp-malware-scan.sh** — Read-only WordPress backdoor/webshell scanner covering three infection classes by shape (not a fixed IOC list):
  - Fake-plugin eval loaders (obfuscated `.txt`, odd plugin dirs, oversized steg README)
  - XOR backdoors (`hex2bin()`^key driven via `$_COOKIE`)
  - eval-cookie webshells dropped in `uploads/`
  - Optional `wp core verify-checksums` integrity pass (`--verify-core`)
  - Optional `--ioc-file=` for private, non-committed custom signatures
  - Scans all sites, `--site=`, or `--path=`; exit 1 on any finding
- **wp-malware-quarantine.sh** — Non-destructive evidence backup before cleanup: tars suspect paths, dumps DB (wp-cli → mysqldump fallback), snapshots `active_plugins`, stores under `/root/malware-quarantine/`, installs 30-day purge cron
- **wp-decode-payload.php** — Static (print-only, never `eval`) decoder for fake-image steg payloads used by the eval-loader family

## [1.3.0] — 2026-07-16

### Added

- **harden-abuseipdb.sh** — Automated Fail2Ban + AbuseIPDB integration:
  - Reads API key from `/var/lib/abuseipdb/.env` (never committed to repo)
  - Extends Fail2Ban with 3 additional jails: `apache-badbots`, `apache-overflows`, `apache-noscript`
  - Detects platform (RunCloud vs litesoup/Apache) and uses correct log paths
  - Configures AbuseIPDB reporting action on all bans (categories: 14/15/18/22)
  - Installs blacklist sync script + cron (every 6h)
  - Idempotent: safe to re-run
  - Supports `--dry-run` for preview

- **abuseipdb-blacklist-sync.sh** — Standalone blacklist sync script:
  - Downloads AbuseIPDB blacklist (confidence >= 75%, limit 500 IPs)
  - Blocks IPs via nftables, firewalld, or iptables (auto-detected)
  - Reads API key from `.env` file (shared with installer)
  - Logs to `/var/lib/abuseipdb/sync.log`

### Fixed

- **abuseipdb-blacklist-sync.sh**: Graceful handling of HTTP 429 (rate limit) — skips cycle instead of failing
- **harden-abuseipdb.sh**: Include `runcloud-agent` and `sshd-ddos` jails in AbuseIPDB action wiring
- **abuseipdb-blacklist-sync.sh**: Reduce cron frequency to every 12h to stay within API rate limits
- **harden-abuseipdb.sh**: Single `logpath` per jail (fail2ban rejects duplicate keys)
- **harden-abuseipdb.sh**: Double-`%%` escape in actionban `printf` format string

- **harden-abuseipdb.sh**: Single `logpath` per jail (fail2ban rejects duplicate keys)
- **harden-abuseipdb.sh**: Double-`%%` escape in actionban `printf` format string (fail2ban uses `%` as escape char)

## [1.2.0] — 2026-07-10

### Added

- **wp-ownership-audit.sh** — Scans WordPress webapps for file ownership issues

### Fixed

- **wp-health-check.sh**: Use FPM PHP binary for wp-cli probe

## [1.1.0] — 2026-07-04

### Added

- `wp-ownership-audit.sh` — File ownership audit script

## [1.0.0] — 2026-06-27

### Added

- Initial scripts: wp-health-check, wp-vuln-check, wp-update, wp-security-audit, litespeed-lockdown
