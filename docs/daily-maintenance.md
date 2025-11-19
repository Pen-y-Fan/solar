# Daily Maintenance Log (Performance Cadence)

This log provides a lightweight, human‑readable trace of our Maintenance cadence decisions and actions.

How to use:
- Run the decision gate on days you’re active on perf‑affecting work: `composer perf-suggest` (default compares to origin/main).
- If exit code = 10 OR a PR is labeled `perf-run-needed`, execute the Medium cadence per `docs/performance-testing.md` and summarize below.
- Only add a new row when you ran the gate or took an action that day — to avoid no‑op commits.
- For the monthly Large advisory (first Monday UTC), add a row and include a brief p95/RPS/error note (no artifacts).

Legend:
- Gate: perf-suggest exit code (0 or 10)
- PR label: whether any open PR has `perf-run-needed`
- Medium: 0/2 (skipped or first pass), 2/2 (two clean runs), or “run+remediate”
- Outcome: kept baseline / refreshed baseline / remediation opened

| Date (UTC) | Gate | PR label | Medium | Outcome | Large advisory | Profiling spot‑check | Notes / Link | Initials |
|---|---:|:---:|:---:|:---|:---:|:---:|:---|:--:|
| 2025‑11‑19 | 0 | no | 0/2 | kept baseline | n/a | no | Entered Maintenance; no runs needed today | MP |

Quick refs:
- Decision gate: `composer perf-suggest` (exit code 0/10)
- Medium run (if required): `APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/run-all.sh` (twice)
- Profiling (optional; not for p95 compare): `export PERF_PROFILE=true` and keep caches/downsampling OFF; inspect `storage/logs/laravel-YYYY-MM-DD.log`
- Policy and tolerances: see `docs/performance-testing.md` (Maintenance Plan)
