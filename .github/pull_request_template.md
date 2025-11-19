## Summary

Describe the purpose of this PR and the changes introduced.

## Checklist

- [ ] Code style, static analysis, and tests pass locally: `composer all`
- [ ] Updated relevant docs if behavior or flags changed
- [ ] For performance-sensitive changes, I ran or scheduled Medium perf checks (see below)

## Performance Checklist (Medium dataset)

This section is required when changes touch any of these areas:
- `app/Filament/`
- `app/Application/Queries/`
- `app/Domain/*/Repositories/`
- `routes/`

Steps:
- [ ] Label this PR with `perf-run-needed`
- [ ] Run the helper to see if a perf run is suggested: `composer perf-suggest [<base_ref>]`
- [ ] If suggested, re-seed and run local Medium perf suite:
  - `PERF_DATASET_SIZE=medium php artisan migrate:fresh --seed --seeder=PerformanceSeeder`
  - `APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/run-all.sh`
- [ ] If two consecutive runs are within tolerance, refresh Medium baselines and add a short note to `docs/performance-testing.md` under “Medium baseline results (Local)” justifying the change
- [ ] Otherwise, open follow-up remediation tasks as needed

Artifacts: Do not commit k6 output; keep only the summarized notes in docs.
