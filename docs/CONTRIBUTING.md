# Contributing to Solar

This is a **personal project**. Contributions are **not** required. Anyone interested is welcome to fork or clone for
your own use. This document highlights a few project conventions and maintainer notes, with a
focus on performance testing workflow.

## Quality Gate (local)

Before opening or updating a PR, always verify the full quality suite locally:

```shell
composer all
```

This runs code style (PSR-12), static analysis (PHPStan), and PHPUnit tests.

## Performance Testing — Maintainer Notes

Performance is tracked with a Medium dataset baseline and an advisory Large dataset run. Read the full policy and
procedures in `docs/performance-testing.md`. This section summarizes the actions maintainers should take on relevant
PRs.

### When a perf run is needed

- If your change touches any of the following, treat it as performance‑sensitive:
    - `app/Filament/`
    - `app/Application/Queries/`
    - `app/Domain/*/Repositories/`
    - `routes/`
- Apply the `perf-run-needed` label to the PR.
- Use the helper to detect whether a perf run is suggested (exits with code 10 when suggested):

```
composer perf-suggest [<base_ref>]  # defaults to origin/main
```

### How to run the Medium perf suite locally

1) Re-seed the Medium dataset:

```shell
PERF_DATASET_SIZE=medium php artisan migrate:fresh --seed --seeder=PerformanceSeeder
```

2) Execute the k6 suite:

```shell
APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/run-all.sh
```

3) Repeat once more to confirm tolerance; ensure 0% error rate on both runs.

4) Decision:

- If both runs are within tolerance, refresh the affected Medium baselines and add a short justification under “Medium
  baseline results (Local)” in `docs/performance-testing.md`.
- If results are outside tolerance on two consecutive runs, open remediation tasks and link them in
  `docs/performance-testing.md`.

Artifacts: Do not commit k6 output; only commit baseline JSON updates (when applicable) and the summarized notes.

### PR Template: Performance Checklist

The PR template includes a “Performance Checklist (Medium dataset)” section with the steps above and labeling guidance.
Please review and complete it for performance‑sensitive changes.

## Monthly Large Advisory Run (local)

Once per month (first Monday recommended), run the Large dataset advisory suite locally to validate capacity under
background load. Record only summarized metrics in `docs/performance-testing.md` (p95/RPS/error rate). Do not commit
artifacts.

## Documentation

- Primary reference: `docs/performance-testing.md`
- Task tracking: `docs/tasks.md`

If you identify gaps or clarifications needed, please open a docs PR.
