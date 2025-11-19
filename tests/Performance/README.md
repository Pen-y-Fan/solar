# Performance Tests (k6)

This directory contains k6 performance test scripts and helpers.

Policy:
- Substantive performance testing (medium/large datasets, profiling, and code improvements) is local-only against `APP_URL=https://solar-dev.test`.
- CI performance checks are PR-only, use a small dataset, and are permanently informational (non-blocking). They must never be promoted to a required or blocking status check.

Quick start options:

0) Easiest: run all core scenarios with auto Docker fallback (no local k6 install needed):
```
APP_URL=https://solar-dev.test VUS=5 DURATION=30s bash tests/Performance/run-all.sh
```

0.1) Strategies resource only (helper script; includes cache clear, feature flag guards, Docker fallback):
```
APP_URL=https://solar-dev.test VUS=5 DURATION=30s bash scripts/run-strategies-perf.sh --repeat 2
# Optional fresh seed:
APP_URL=https://solar-dev.test bash scripts/run-strategies-perf.sh --seed-medium
```

Medium dataset local baseline:
```
PERF_DATASET_SIZE=medium php artisan migrate:fresh --seed --seeder=PerformanceSeeder
APP_URL=https://solar-dev.test VUS=5 DURATION=30s bash tests/Performance/run-all.sh
# Artifacts will be suffixed by dataset size, e.g., perf-reports/forecasts.medium.summary.json
```

1) Using Docker (per-scenario):
```
docker run --rm -i -e APP_URL=https://solar-dev.test grafana/k6 run - < tests/Performance/smoke.k6.js
```

2) Using a local k6 binary (per-scenario):

- Install k6: https://k6.io/docs/get-started/installation/
- Run:
```
k6 run tests/Performance/smoke.k6.js
```

Authentication (local dev):

- A local-only bootstrap endpoint exists to obtain a Laravel session cookie for authenticated tests.
- Seed the database (at least once):
```
php artisan migrate:fresh --seed --seeder=PerformanceSeeder
```
- Then get a session cookie (replace domain if needed):
  - Using helper script:
    ```
    # plain cookie value
    bash scripts/print-session-cookie.sh

    # as a curl header
    bash scripts/print-session-cookie.sh header

    # as a curl flag
    curl $(bash scripts/print-session-cookie.sh curl) https://solar-dev.test/
    ```
  - Or manual curl:
    ```
    curl -i https://solar-dev.test/_auth/bootstrap
    ```
  - Copy the `laravel_session` cookie from the `Set-Cookie` response header when using manual curl; k6 will automatically reuse cookies for the same domain.
  - The scripts will call this endpoint automatically when `USE_BOOTSTRAP_AUTH=true` (default), so you typically don't need to pass cookies explicitly.

Strategy generation flow (optional Livewire path):

- The `strategy-generation.k6.js` scenario can either:
  - Trigger a local-only helper endpoint (`/_perf/generate-strategy`) to dispatch the CQRS command; or
  - Post to a Livewire endpoint when `STRAT_GEN_LIVEWIRE=true` and `LIVEWIRE_ENDPOINT`/`LIVEWIRE_PAYLOAD_BASE64` are provided.
- To use the Livewire path, first open the Strategies page in a browser and capture a real Livewire request body; base64-encode it and pass via `LIVEWIRE_PAYLOAD_BASE64`.

Environment variables:

- `APP_URL` (default: https://solar-dev.test)
- `AUTH_HEADER` (optional: e.g., `Bearer <token>`) — not required when using session bootstrap
- `USE_BOOTSTRAP_AUTH` (default: true) — for scripts that support automatic login
- `VUS`, `DURATION` — override defaults per script
- `PERF_DATASET_SIZE` — optional; used by seeders and to suffix artifact names (e.g., `medium`, `large`)
- `STRAT_GEN_LIVEWIRE` — set to `true` to enable the Livewire POST path in `strategy-generation.k6.js` (default: false)
- `LIVEWIRE_ENDPOINT` — path to the Livewire endpoint (e.g., `/livewire/message/strategies.table`)
- `LIVEWIRE_PAYLOAD_BASE64` — base64-encoded JSON body for the Livewire POST
- `STRATEGY_PERIOD` — optional period parameter for local helper path (e.g., `today`, `week`)
- `STRAT_GEN_CONCURRENT_POSTS` — when `true`, all VUs will POST the generation endpoint; default `false` means only one VU posts to avoid duplicate concurrent generations

Examples:
```
# Run all core scenarios and export summaries to perf-reports/
APP_URL=https://solar-dev.test VUS=5 DURATION=30s bash tests/Performance/run-all.sh

# Strategies scenario with helper script (two consecutive runs)
APP_URL=https://solar-dev.test VUS=5 DURATION=30s bash scripts/run-strategies-perf.sh --repeat 2

# Smoke test (local k6)
APP_URL=https://solar-dev.test k6 run tests/Performance/smoke.k6.js

# Dashboard scenario with auto-auth bootstrap (local k6)
APP_URL=https://solar-dev.test k6 run tests/Performance/dashboard.k6.js

# Using Docker directly
docker run --rm -i -e APP_URL=https://solar-dev.test grafana/k6 run - < tests/Performance/dashboard.k6.js
```

Notes:
- run-all.sh will use your local k6 if present, otherwise it will fall back to Docker (grafana/k6) automatically.
- scripts/run-strategies-perf.sh behaves similarly, and also sets deterministic feature flags and can re-seed Medium on demand.
- Keep test datasets deterministic and seeded. See docs/performance-testing.md for seeding guidance.
- For CI smoke runs, prefer short durations and low VUs.

Profiling vs. baselines:

- Toggle `PERF_PROFILE=true` only when you intentionally want lightweight SQL logging for local triage. With profiling ON, Laravel will emit debug SQL lines and overall latencies may shift.
- Do not compare p95/p90 values against committed Medium baselines when `PERF_PROFILE=true`. Use profiling runs strictly to surface slow queries and N+1s. For baseline comparisons, ensure `PERF_PROFILE` is unset/false and rerun the scenario twice with 0% HTTP errors.

Avoiding bootstrap probe noise during profiling:

- Some helper flows auto‑bootstrap an authenticated session via a local‑only endpoint. Immediately after cache clears or when Livewire views compile lazily, an incidental dashboard probe can occur and register a transient HTTP error in k6 while profiling.
- To keep `http_req_failed` at 0% during profiling:
  - Option A: set `USE_BOOTSTRAP_AUTH=false` for that run to skip the auxiliary probe, e.g.,
    ```
    USE_BOOTSTRAP_AUTH=false PERF_PROFILE=true APP_URL=https://solar-dev.test \
      VUS=5 DURATION=30s bash scripts/run-strategies-perf.sh
    ```
  - Option B: pre‑warm once in a browser before the run (open the Dashboard to let Livewire/Blade compile), then execute your profiling run with `PERF_PROFILE=true`.
  - Regardless of option, keep feature caches OFF and clear route/config caches when preparing clean baselines.
