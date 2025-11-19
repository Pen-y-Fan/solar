# Performance Profiling Guide (Local Development)

This guide shows how to profile and investigate slow endpoints locally when running k6 scenarios or manual browsing.
It focuses on low‑overhead techniques that work with the current project setup.

## 1) Enable SQL Query Logging (DB::listen)

You can temporarily enable SQL query logging in a local environment to understand query counts and durations.

Option A — Tinker on demand:

```bash
php artisan tinker
>>> \DB::listen(function ($query) { dump([$query->time.' ms', $query->sql, $query->bindings]); });
```

Keep Tinker running while you execute requests (via browser or k6). You will see queries printed to the console.

Option B — Minimal local toggle (recommended for a session only):

1. Set an environment variable in your local shell (do not commit):
   ```bash
   export SQL_DEBUG=1
   ```
2. Then run the app/tests from the same shell. In your local service provider, you can wrap a listener like this:

   ```php
   // Example snippet: place temporarily inside AppServiceProvider::boot() on local only
   if (app()->environment('local') && (bool) env('SQL_DEBUG', false)) {
       \DB::listen(function ($query) {
           logger()->debug('SQL', [
               'time_ms' => $query->time,
               'sql' => $query->sql,
               'bindings' => $query->bindings,
           ]);
       });
   }
   ```

Remove the snippet or set `SQL_DEBUG=0` when done to avoid log noise. Prefer keeping this as a temporary local change.

Option C — Built‑in toggle via config (recommended):

1. Use the provided config flag which wires `DB::listen()` when `APP_ENV` is `local` or `testing`:
   ```bash
   export PERF_PROFILE=true
   ```
2. The listener is enabled by `config/perf.php => 'profile'` and AppServiceProvider:
   ```php
   // config/perf.php
   return [
       'profile' => env('PERF_PROFILE', false),
       // ...
   ];
   ```
   ```php
   // AppServiceProvider::boot()
   if (app()->environment(['local', 'testing']) && (bool) config('perf.profile', false)) {
       DB::listen(static function (QueryExecuted $query): void {
           Log::debug('sql', [
               'sql' => $query->sql,
               'bindings' => $query->bindings,
               'time_ms' => $query->time,
               'connection' => $query->connectionName,
           ]);
       });
   }
   ```
3. Unset or set `PERF_PROFILE=false` when finished.

What to look for:

- N+1 patterns (same query repeated many times)
- Slow queries (>50–100ms on local)
- Missing indexes (full scans on large tables)

### Implemented

Option C has been implemented in the project.

- Enabled a clean, toggleable SQL logging path by adding `profile` => `env('PERF_PROFILE', false)` to `config/perf.php`
  so `AppServiceProvider`’s `DB::listen()` activation works via `PERF_PROFILE=true` in local/testing. Results of queries
  are logged to `storage/logs/laravel-yyyy-mm-dd.log`.

## 2) Laravel Telescope or Clockwork (optional, local-only)

Either tool can help inspect requests, queries, and timings in-depth during development.

- Telescope: https://laravel.com/docs/12.x/telescope
- Clockwork: https://underground.works/clockwork/

Suggested setup (local only):

- Require the chosen package in `require-dev`.
- Enable the service provider only in `local` environment.
- Do not enable in CI or production.

Use cases:

- Inspect per-request query lists and durations
- Identify slow endpoints and cache opportunities

## 3) Route and Config Caching

In local profiling, test the impact of enabling caches:

```bash
php artisan route:clear && php artisan config:clear
php artisan route:cache && php artisan config:cache
```

When done testing, you can clear caches again:

```bash
php artisan optimize:clear
```

Note: Ensure you rebuild caches after changing routes or config.

## 4) DB Index Review Checklist

Run through this list for slow queries:

- Are WHERE clause columns indexed?
- Are JOIN keys indexed on both sides?
- Is the ORDER BY column indexed when sorting large sets?
- Would a composite index help for common filters? (order matters)
- Are there unnecessary wildcard LIKE patterns preventing index use?

## 5) Workflow: From k6 to Fix

1. Seed perf dataset:
   ```bash
   php artisan migrate:fresh --seed --seeder=PerformanceSeeder
   ```
2. Run a scenario and capture a baseline:
   ```bash
   APP_URL=https://solar-dev.test k6 run tests/Performance/dashboard.k6.js
   ```
3. If thresholds fail locally or latencies regress, enable SQL logging (section 1) and re-run.
4. Identify the top offenders (queries or missing caches) and create tickets in `docs/tasks.md` under Performance.
5. Apply fixes (eager loading, indexing, caching), then re-run k6 and update baselines under
   `tests/Performance/baselines/`.

## 6) Tips

- Use `Carbon::setTestNow()` in seeders to keep data deterministic.
- Prefer `QUEUE_CONNECTION=sync` during local perf checks unless testing queues.
- Keep browser tabs and background apps minimized during baseline runs for consistency.

## 7) Findings Summary (Current)

- No N+1 observed in core flows during Medium dataset profiling:
    - Forecasts: index and widget/chart queries executed with stable query counts; no repeated per-row lookups detected.
    - Inverters: chart/series endpoints operate without per-record relationship lookups; stable query plan confirmed.
    - Strategies: index and edit views, including `StrategyPerformanceSummaryQuery`, show no N+1 patterns under Medium.
- If data shape or relations change, re-run Section 1 (SQL logging) and update this summary accordingly.
