# Production deployment (Docker + Cloudflare Tunnel)

This stack runs the Solar app behind a Cloudflare Tunnel with **no ports
published to the host** — nothing listens on this machine's public IP, so
there's nothing to scan or attack directly. All inbound traffic arrives via
an outbound connection `cloudflared` makes to Cloudflare's edge.

Files:
- `docker-compose.prod.yml` (repo root) — app (PHP-FPM+nginx) + cloudflared
- `docker/production/Dockerfile` — multi-stage build, runs as non-root
- `.env.docker.example` (repo root) — copy to `.env` and fill in

Database: **SQLite**. The real `database.sqlite` file lives on the host at
`./database/database.sqlite` and is bind-mounted into the container, so it
persists independently of the image and survives rebuilds.

## 1. First-time setup

```bash
cp .env.docker.example .env
```

Place your real database at `database/database.sqlite` (this repo path is
already gitignored — never commit it).

Generate an app key (the container has PHP but no `.env` yet, so run it
against the built image directly):

```bash
docker compose -f docker-compose.prod.yml build app
docker run --rm --entrypoint php solar-app:prod artisan key:generate --show
```

Paste the `base64:...` output into `APP_KEY=` in `.env`. Also fill in:
- `APP_URL` — the public hostname you'll use (e.g. `https://solar.zilchy.link`)
- `CLOUDFLARE_TUNNEL_TOKEN` — see step 2
- Your Solcast / Octopus / Solis API credentials

**Never commit `.env`.** It's already covered by `.gitignore`.

## 2. Create the Cloudflare Tunnel

1. Cloudflare dashboard → **Zero Trust** → **Networks** → **Tunnels** → **Create a tunnel** (Cloudflared type).
2. Name it (e.g. `solar`), and copy the tunnel token shown in the install step
   (a long string starting `eyJ...`) into `CLOUDFLARE_TUNNEL_TOKEN` in `.env`.
   You do NOT need to run the install command it suggests — the `cloudflared`
   container in `docker-compose.prod.yml` does that.
3. In the same tunnel's **Public Hostname** tab, add a hostname (e.g.
   `solar.zilchy.link`) pointing at:
   - Type: `HTTP`
   - URL: `app:8080`

   (`app` is the docker-compose service name; `cloudflared` resolves it via
   the internal `edge` network — no IP addresses needed.)
4. Save. Cloudflare will start routing that hostname through the tunnel as
   soon as the `cloudflared` container connects (step 3 below).

## 3. Bring the stack up

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml ps
```

Both services (`app`, `cloudflared`) should show healthy/running.

## 4. Database

Your real `database.sqlite` (bind-mounted from `./database/database.sqlite`)
already contains your schema and data — nothing to import. If the schema
predates recent code changes, bring it up to date (safe, idempotent, does
not touch existing rows):

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

Migrations are intentionally **not** run automatically on container boot, so
your real data is never touched without you asking for it.

## 5. Verify

```bash
docker compose -f docker-compose.prod.yml logs -f cloudflared   # look for "Registered tunnel connection"
curl -I https://solar.zilchy.link/up                            # should be 200
```

Also confirm the tunnel has exactly **one** connector (see Troubleshooting below):

```bash
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/config -v ./.cloudflared:/config/.cloudflared cloudflare/cloudflared:2026.8.2 tunnel info solar
```

## Troubleshooting: intermittent 502s (works for some visitors, not others)

**Check this first, before suspecting DNS, your ISP, IPv6, VPNs, or Cloudflare's
routing.** If the app itself is healthy but some visitors/networks get a 502
"Host Error" while others load fine, the overwhelmingly likely cause is a
**second `cloudflared` serving the same tunnel**.

```bash
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/config -v ./.cloudflared:/config/.cloudflared cloudflare/cloudflared:2026.8.2 tunnel info solar
```

There must be exactly one CONNECTOR ID listed. If there are two, find and
remove the stray container (`docker ps -a | grep cloudflared`), then re-run the
command — Cloudflare's edge deregisters the dead connections within ~20s.

**Why it presents so confusingly:** Cloudflare load-balances requests across
*every* registered connection. A stray connector that can't reach `app:8080`
(e.g. it's on the default bridge network instead of the compose `edge` network)
fails every request routed to it, while the real connector serves the rest. Which
Cloudflare datacenter a visitor enters at decides which connector they get — and
that's stable per network path, so it looks convincingly like a geography, ISP,
or VPN-reputation problem. It isn't.

**How a stray one gets created:** `timeout 30 docker run … cloudflared tunnel run`
or `docker run … &` followed by `kill` **does not stop the container** — those
signals go to the `docker` CLI client, which detaches and leaves the container
running. Always use `docker run --rm -d --name …` plus an explicit
`docker rm -f <name>` for throwaway tunnel tests, and check `docker ps`
afterwards. (This cost several hours on 2026-08-18.)

Stray connectors also show up in the `CONNECTIONS` column of
`cloudflared tunnel list` — a healthy single-connector tunnel here shows 4
connections; noticeably more than that means more than one connector.

## Resilience — recovering from a hard crash / power loss automatically

Three layers, each covering a different failure mode:

1. **Box loses power, or reboots.** Docker itself is a systemd service
   (`systemctl enable docker`, already the default on most installs) and
   compose's `restart: unless-stopped` policy is set on both `app` and
   `cloudflared`. On boot, systemd starts Docker, and Docker restarts every
   container that was running when it last stopped — no manual step needed,
   as long as you never ran `docker compose down` (which removes the
   containers) before the crash. `docker compose stop`/a plain reboot are
   both fine; `down` is not.
2. **A container hangs without crashing** (e.g. all php-fpm workers wedged,
   process still alive). Docker's own restart policy only reacts to a
   process actually exiting, so a hang is invisible to it. A small watchdog
   (`docker/production/healthcheck-watchdog.sh`, running under supervisord
   inside the `app` container) polls `/up` every 15s; after 5 consecutive
   failures it kills supervisord (pid 1), which exits the container and lets
   `restart: unless-stopped` bring it back up clean. Tuned via
   `WATCHDOG_INTERVAL`/`WATCHDOG_MAX_FAILURES`/`WATCHDOG_URL` env vars if you
   want to change the sensitivity. (Deliberately not a Docker `autoheal`
   sidecar — that pattern needs a `/var/run/docker.sock` mount, which hands
   the sidecar host-level control over every container. The in-process
   watchdog gets the same self-healing without that exposure.)
3. **Power is cut mid-write to the SQLite file.** The entrypoint enables
   SQLite's WAL journal mode on every boot (idempotent) — writes go to a
   separate log and get checkpointed later instead of rewriting the main
   file in place, which is far less likely to corrupt on an abrupt power
   loss than the default rollback-journal mode. On top of that, `app:backup-database`
   runs daily via Laravel's scheduler (already running in the container):
   it forces a WAL checkpoint, copies the database to
   `storage/app/backups/database-<timestamp>.sqlite` (bind-mounted to
   `./storage/app/backups` on the host — a separate file from the live
   database, so it survives even if that one gets corrupted), and prunes
   anything past the last 14 days. Run it manually any time with:
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan app:backup-database
   ```

Net effect: a hard crash recovers on its own without you touching the
machine, and the worst case if something still goes wrong with the live
database is restoring the most recent daily backup — never a total loss.

## Security notes

- **No `ports:` are published anywhere** in `docker-compose.prod.yml` —
  nothing but `cloudflared` can ever reach the `app` container, over the
  internal-only `edge` docker network.
- `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE_COOKIE=true`
  are hardcoded in the compose file's `environment:` block, overriding
  whatever is in `.env` — this can't accidentally be left in debug/local mode.
- The app, nginx, and php-fpm all run as a non-root user inside the
  container.
- Laravel's proxy trust is configured for the tunnel — see
  [`bootstrap/app.php`](../../bootstrap/app.php).
- A background OWASP Top 10 review of this codebase found and fixed: a
  dev-only unauthenticated login route now only registers outside
  local/testing, the seeders that create a well-known default password now
  refuse to run in production, and the Solcast API key is no longer written
  to the logs. See git history on `routes/web.php`,
  `database/seeders/DatabaseSeeder.php`,
  `database/seeders/PerformanceSeeder.php`, and
  `app/Domain/Forecasting/Actions/*.php` for the exact diffs.
- TOTP-based two-factor auth (via Filament's native "App Authentication") is
  required for every login — see `app/Providers/Filament/AppPanelProvider.php`.
- Still worth doing yourself: periodically bump `composer.lock` /
  `package-lock.json` for security patches, and keep a backup of
  `database/database.sqlite` somewhere other than this host.

## Updating

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

## Logs

Application, nginx, and php-fpm logs all go to stdout/stderr, viewable with:

```bash
docker compose -f docker-compose.prod.yml logs -f app
```
