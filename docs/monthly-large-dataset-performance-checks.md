# Monthly Large Dataset Performance Checks

Last run: Wed 3-Dec-2025 19:08

Purpose: Provide a repeatable, check‑off task to run the Large dataset advisory performance checks locally each month
and record summarized metrics in `docs/performance-testing.md` under “Large Dataset Advisory Results (Local)”. This
enables progress tracking and easy restart from a known point.

References:

- User request: docs/user-requests.md → “Monthly large dataset performance checks”
- Performance guide: docs/performance-testing.md (target section: “Large Dataset Advisory Results (Local)”)
- Project guidelines: .junie/guidelines.md

Notes:

- k6 and npm are installed locally and can be run from the command line.
- Regularly run `composer all` during the task to ensure code quality, static analysis, and tests all pass.

## Cadence

- [x] Run on the first Monday of each month (UTC). If missed, run at the earliest opportunity within the same month.

## Prerequisites

- [x] Local dev environment is set up per `.junie/guidelines.md` (Laravel Herd, PHP 8.2+, Composer)
- [x] Dependencies installed: `composer install`, `npm ci` (or `npm install`) completed previously
- [x] App key generated and database migrations can run locally
- [x] k6 is available in PATH (`k6 version`)

## Safety and scope

- [x] Run on a local environment only; do not commit load test artifacts (e.g., k6 output JSON, screenshots).
- [x] Keep feature caches and downsampling flags OFF unless explicitly profiling a targeted change.

## Procedure

1) Prepare database (Large dataset)
    - [x] Refresh and seed with Large dataset:
      ```bash
      PERF_DATASET_SIZE=large php artisan migrate:fresh --seed --seeder=PerformanceSeeder
      ```
2) Run k6 suite locally against dev URL
    - [x] Execute the full local performance suite for Large dataset:
      ```bash
      APP_URL=https://solar-dev.test \
      VUS=8 \
      DURATION=90s \
      PERF_DATASET_SIZE=large \
      bash tests/Performance/run-all.sh
      ```
3) Summarize results
    - [x] For each scenario, collect and summarize: p95 latency, requests-per-second (RPS), and error rate.
    - [x] Paste a concise summary into `docs/performance-testing.md` under “Large Dataset Advisory Results (Local)”,
      including date (UTC), environment, k6 VUs/duration, and any notable observations.
    - [x] Do not commit raw artifacts; only the summary text belongs in the repo.
4) Quality checks (run regularly during the task)
    - [x] Run full suite locally to ensure quality:
      ```bash
      composer all
      ```
    - [ ] If style issues are reported, optionally fix:
      ```bash
      composer cs-fix
      ```
5) Close out
    - [x] Commit doc updates to `docs/performance-testing.md` with a message like: "perf: add monthly Large dataset
      advisory summary (YYYY-MM)".
    - [x] Review `docs/tasks.md` item 1.1.4 and mark as completed for this month if applicable.

## Restart guide (if interrupted)

- Resume from the last unchecked step in “Procedure”. If the database state is uncertain, re-run step 1 to refresh and
  seed.
- If k6 failed midway, re-run step 2; keep the same `VUS`/`DURATION` to maintain comparability.

## Acceptance criteria

- [x] Large dataset re-seeded and test suite executed successfully
- [x] Summary added to `docs/performance-testing.md` with p95, RPS, error rate per scenario
- [x] No artifacts committed; repository passes `composer all`
