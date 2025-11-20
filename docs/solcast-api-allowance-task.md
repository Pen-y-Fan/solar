
# Solcast API Allowance — Detailed Task Plan (TDD/DDD)

This task plan turns the user story in `docs/solcast-api-allowance.md` into an actionable, checkbox‑driven roadmap. It
follows `.junie/guidelines.md`, emphasizes TDD and DDD/CQRS conventions used in this project, and includes explicit
quality gates. Each stage ends with a "Quality Gate" step to run `composer all`. The final stage runs the K6 Medium
performance tests and updates `docs/k6-medium-maintenance.md`.

Notes:
- Keep commits small and incremental. Check off items as you go (`[x]`).
- If you must pause the task, resume from the last unchecked box.
- Domain code under `app/Domain` must be covered by automated tests per guidelines.

## References and Definitions

- Source story: `docs/solcast-api-allowance.md`
- Quality suite: `composer all` (code style, static analysis, tests)
- Test coverage commands: see `.junie/guidelines.md` → Testing Information
- Performance (Medium cadence): see `docs/k6-medium-maintenance.md` and `docs/performance-testing.md`
- Timezone: `SOLCAST_RESET_TZ` (default `UTC`) — daily reset boundary
- Endpoints: `forecast`, `actual` (combined daily cap)

## Preconditions

- [x] Ensure local environment is set up (Laravel Herd; see `.junie/guidelines.md` → Setup) and DB migrated
      for current project state.
- [x] Create a working branch for this feature, e.g., `feature/solcast-allowance`.

## Stage 1 — Data Model and Migrations (DDD foundation)

- [x] Create migration for `solcast_allowance_states` singleton table with fields from the story:
      `day_key`, `count`, `last_attempt_at_forecast`, `last_attempt_at_actual`, `last_success_at_forecast`,
      `last_success_at_actual`, `backoff_until`, `reset_at`, timestamps.
- [ ] Optional: Create migration for `solcast_allowance_logs` with fields from the story; include sensible indexes
      (e.g., `endpoint`, `happened_at`, `day_key`).
- [x] Add Eloquent model `App\Domain\Forecasting\Models\SolcastAllowanceState` with helpers for day transitions.
- [ ] Optional: Add Eloquent model `App\Domain\Forecasting\Models\SolcastAllowanceLog` (with pruning policy constants).
- [x] Write unit tests for models (factories or simple programmatic creation) covering getters/setters and day reset behavior.
- [x] Run migrations locally to validate schema.
- [x] Quality Gate: run `composer all` and ensure green.

## Stage 2 — Value Objects and Enums

- [ ] Add `Endpoint` enum at `App\Domain\Forecasting\ValueObjects\Endpoint` with values `FORECAST`, `ACTUAL`.
- [ ] Add `AllowanceStatus` value object to describe current status (budget left, backoff, resetAt, last attempts/successes).
- [ ] Add `AllowanceDecision` value object (allowed: bool, reason: string/enum; optional nextEligibleAt).
- [ ] Unit tests for the above VOs (immutability, simple construction, and helpers like remaining budget computation).
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 3 — Domain Service (Allowance Policy + Concurrency)

- [ ] Implement `App\Domain\Forecasting\Services\SolcastAllowanceService` encapsulating:
      - [ ] Config parsing (cap, min intervals, backoff duration, reset TZ).
      - [ ] Reset window computation using `Carbon` in `SOLCAST_RESET_TZ`.
      - [ ] `checkAndLock(Endpoint $endpoint, bool $forceMinInterval = false): AllowanceDecision` using DB transaction
            and `lockForUpdate()` against the singleton row, applying policy precedence:
            backoff → reset (if now >= reset_at) → daily cap → min-interval (unless forced) → record attempt reservation.
      - [ ] `recordSuccess(Endpoint $endpoint): void` in a short transaction.
      - [ ] `recordFailure(Endpoint $endpoint, int $status): void` (429 → set global backoff; always increment count).
      - [ ] `currentStatus(): AllowanceStatus`.
- [ ] Unit tests simulating edge cases: just before/after reset, hitting daily cap, min interval, backoff on 429,
      and concurrent callers (simulate with sequential calls under lock semantics).
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 4 — Domain Events and Observability

- [ ] Add events under `App\Domain\Forecasting\Events\*`:
      `SolcastRequestAttempted`, `SolcastRequestSucceeded`, `SolcastRequestSkipped`, `SolcastRateLimited`,
      `SolcastAllowanceReset`.
- [ ] Emit events from the service during transitions (attempt, skip reason, success, 429/backoff, reset).
- [ ] Add listeners/logging hooks (structured logs) and minimal tests for event dispatch (using Laravel events fakes).
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 5 — Application Layer (Commands + Handlers)

- [ ] Add Commands:
      - [ ] `App\Application\Commands\Forecasting\RequestSolcastForecast` (DTO; includes `force` flag).
      - [ ] `App\Application\Commands\Forecasting\RequestSolcastActual` (DTO; includes `force` flag).
- [ ] Add Handlers under `...\Handlers\` for each command to:
      - [ ] Resolve `SolcastAllowanceService`.
      - [ ] Call `checkAndLock()` with endpoint; if not allowed, emit skipped event and return ActionResult.
      - [ ] Call existing domain action (`ForecastAction` or `ActualForecastAction`).
      - [ ] Based on response (success/HTTP code), call `recordSuccess` or `recordFailure`.
- [ ] Map Command => Handler in `App\Providers\AppServiceProvider` (CommandBus mappings).
- [ ] Unit tests for handlers (happy path, cap reached, min interval, backoff active, 429).
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 6 — Queries (Read Side)

- [ ] Add `App\Application\Queries\Forecasting\SolcastAllowanceStatusQuery` returning `AllowanceStatus`.
- [ ] Add `App\Application\Queries\Forecasting\NextEligibleTimesQuery` to compute next eligible timestamps per endpoint.
- [ ] Unit tests for query logic using seeded state rows.
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 7 — Filament/UX Integration

- [ ] Update Forecast/Actual Filament widgets to dispatch commands rather than directly invoking actions.
- [ ] Add a small Filament card on the Forecast page showing remaining allowance, next reset, and backoff status.
- [ ] Feature tests for UI flows: dispatch command, assert notifications/messages surface policy outcomes.
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 8 — Configuration and .env

- [ ] Add environment variables with defaults in `config` and `.env.example`:
      `SOLCAST_DAILY_CAP`, `SOLCAST_FORECAST_MIN_INTERVAL`, `SOLCAST_ACTUAL_MIN_INTERVAL`, `SOLCAST_429_BACKOFF`,
      `SOLCAST_RESET_TZ`.
- [ ] Document configuration in `README.md` (brief) and reference the story doc for details.
- [ ] Unit test config parsing in the service (e.g., ISO-8601 duration parsing).
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 9 — Integration Tests (End‑to‑End slices)

- [ ] Write Feature tests that simulate sequences over time: multiple tries within min interval; just before/after daily
      reset; 429 backoff blocking subsequent attempts; success increments; combined cap enforcement across endpoints.
- [ ] Use `Carbon::setTestNow()` and in-memory SQLite per test guidelines.
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 10 — Observability + Admin Surface

- [ ] Ensure events produce structured logs at suitable levels (`info`/`warning`).
- [ ] Add optional log table writes if `solcast_allowance_logs` is enabled; add pruning command or policy.
- [ ] Add an admin page section (Filament) for operational status (read-only view from `AllowanceStatusQuery`).
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 11 — Concurrency/Locking Verification

- [ ] Add a test ensuring `lockForUpdate()` path prevents double increments under concurrent attempts (simulate with
      interleaved transactions in tests or fakes around repository/service boundaries).
- [ ] Review transaction boundaries to avoid holding locks during external API calls; use reservation pattern (attempt
      recorded, commit, perform call, then finalize success/failure in a new short transaction).
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 12 — Documentation and Ops Notes

- [ ] Update `docs/solcast-api-allowance.md` if any deviations/clarifications were made during implementation.
- [ ] Add troubleshooting section (e.g., clock skew, daylight savings in non‑UTC TZs, backoff override policy).
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 13 — Performance Validation (K6 Medium) and Maintenance Log

- [ ] Prepare dataset and ensure app is running locally (see `docs/performance-testing.md`).
- [ ] Execute Medium cadence twice (clean runs):
      ```bash
      APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/run-all.sh
      APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/run-all.sh
      ```
- [ ] Compare against baseline; ensure tolerances per policy are met. If not, open remediation tasks.
- [ ] Update `docs/k6-medium-maintenance.md` with a new row capturing date, gate, runs, and outcome.
- [ ] Quality Gate: run `composer all` and ensure green.

## Stage 14 — Rollout and Clean‑up

- [ ] Re‑run full quality suite.
- [ ] Rebase/merge and resolve conflicts; final review.
- [ ] Ensure `docs/tasks.md` reflects progress (optional cross‑link to this task file).
- [ ] Prepare a PR including this checklist; ensure CI is green.

---

### Acceptance Criteria Summary

- Combined daily cap enforced atomically across forecast/actual.
- Per‑endpoint minimum intervals respected (with `--force` only bypassing min‑interval, not cap/backoff).
- Backoff on 429 applied globally; no further requests during backoff.
- Mutex ensures one in‑flight Solcast call at a time via DB row locks.
- Day resets at `reset_at` computed in `SOLCAST_RESET_TZ`.
- Events/logging and an admin status surface are present.
- All domain code has tests; `composer all` passes at the end of each stage.
- K6 Medium perf tests executed twice; maintenance log updated.
