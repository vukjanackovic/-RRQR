# RR Quick Reaction (RRQR)

WordPress plugin for **The Nation Network**: admin tools to pull NBA schedule/boxscore data and draft “quick reaction” content.

Original plugin: [github.com/nationnetwork/rrqr](https://github.com/nationnetwork/rrqr) — this fork adds an **NBA CDN bridge** (local mirror + REST ingest + optional GitHub Actions sync) when the host cannot reach `cdn.nba.com` directly.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Install

Copy this folder to `wp-content/plugins/rrqr/` and activate **RR Quick Reaction** in the admin.

## NBA bridge (blocked host IP)

1. **Settings → Quick Reactions**: configure ingest secret, optional **Whole site** or **Admin test mode**.
2. **Quick Reactions → Bridge tools**: inspect cache files or try **Download schedule + standings** (only works if *this* server can reach the NBA CDN).
3. **External fetch**: run `tools/rrqr-bridge-fetch.php` from a network that *can* reach the NBA, or use **GitHub Actions** (see below).

### Schedule vs legacy “standings” URL

- **`cdn.nba.com/.../scheduleLeagueV2.json`** — real JSON; safe for server/GitHub Actions.
- **`ca.global.nba.com/.../standing.json`** — often returns **HTTP 200 with an HTML app shell**, not JSON, for automated clients. Your smoke test should flag this. The fetcher **skips** ingesting that response so you do not poison the cache. The child theme’s `summary()` block still expects the old JSON shape; until you switch that widget to another source (e.g. `stats.nba.com` API + mapping, or manual text), standings may not update via the bridge.

## GitHub Actions sync

Repository secret | Purpose
---|---
`RRQR_BRIDGE_SITE` | `https://yoursite.com` (no trailing slash)
`RRQR_BRIDGE_SECRET` | Bearer token (matches WordPress ingest secret)

Optional: `RRQR_BRIDGE_GAME_ID` — 10-digit game id to also push boxscore JSON.

Workflow: `.github/workflows/rrqr-nba-bridge-sync.yml` — run manually under **Actions** or wait for the schedule. If the job fails on “Fetch failed”, the NBA may be blocking GitHub’s runner IPs; use home cron or a VPS instead.

## CLI fetcher

```bash
RRQR_BRIDGE_SITE="https://example.com" RRQR_BRIDGE_SECRET="your-token" php tools/rrqr-bridge-fetch.php
php tools/rrqr-bridge-fetch.php --game=0022400123
```

## License

GPL-2.0-or-later (see `LICENSE`).
