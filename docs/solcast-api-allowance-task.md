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
- Updated info for cap: the cap count should only be incremented when
  a successful response was received. If a 429 response is received the cap should be set to the max (6). If an
  unsuccessful response was received, this should not affect the cap, e.g. if the network was down, time out, or API key
  not set.

## Preconditions

- [x] Ensure local environment is set up (Laravel Herd; see `.junie/guidelines.md` → Setup) and DB migrated
  for current project state.
- [x] Create a working branch for this feature, e.g., `feature/solcast-allowance`.

## Stage 1 — Data Model and Migrations (DDD foundation)

- [x] Create migration for `solcast_allowance_states` singleton table with fields from the story:
  `day_key`, `count`, `last_attempt_at_forecast`, `last_attempt_at_actual`, `last_success_at_forecast`,
  `last_success_at_actual`, `backoff_until`, `reset_at`, timestamps.
- [x] Add Eloquent model `App\Domain\Forecasting\Models\SolcastAllowanceState` with helpers for day transitions.
- [x] Write unit tests for models (factories or simple programmatic creation) covering getters/setters and day reset
  behavior.
- [x] Run migrations locally to validate schema.
- [x] Quality Gate: run `composer all` and ensure green.

## Stage 2 — Value Objects and Enums

- [x] Add `Endpoint` enum at `App\Domain\Forecasting\ValueObjects\Endpoint` with values `FORECAST`, `ACTUAL`.
- [x] Add `AllowanceStatus` value object to describe current status (budget left, backoff, resetAt, last
  attempts/successes).
- [x] Add `AllowanceDecision` value object (allowed: bool, reason: string/enum; optional nextEligibleAt).
- [x] Unit tests for the above VOs (immutability, simple construction, and helpers like remaining budget computation).
- [x] Quality Gate: run `composer all` and ensure green.

## Stage 3 — Domain Service (Allowance Policy + Concurrency)

- [x] Implement `App\Domain\Forecasting\Services\SolcastAllowanceService` encapsulating:
  - [x] Config parsing (cap, min intervals, backoff duration, reset TZ).
  - [x] Reset window computation using `Carbon` in `SOLCAST_RESET_TZ`.
  - [x] `checkAndLock(Endpoint $endpoint, bool $forceMinInterval = false): AllowanceDecision` using DB transaction
  and `lockForUpdate()` against the singleton row, applying policy precedence:
  backoff → reset (if now >= reset_at) → daily cap → min-interval (unless forced) → record attempt reservation.
  - [x] `recordSuccess(Endpoint $endpoint): void` in a short transaction.
  - [x] `recordFailure(Endpoint $endpoint, int $status): void` (429 → set global backoff; always increment count).
  - [x] `currentStatus(): AllowanceStatus`.
- [x] Identified why the daily cap was being consumed despite unsuccessful requests:
    `SolcastAllowanceService::checkAndLock()` increments the daily `count` up-front (reservation). Because
    `recordFailure()` did not revert the reservation, any non‑429 failure (e.g., API key missing, timeouts, network) would
    still permanently consume allowance. This matches the behavior you observed in the logs where repeated unsuccessful
    runs led to `solcast.skipped {"reason":"daily_cap_reached"}`.
- [x] Unit tests simulating edge cases: just before/after reset, hitting daily cap, min interval, backoff on 429,
  and concurrent callers (simulate with sequential calls under lock semantics).
- [x] Quality Gate: run `composer all` and ensure green.
- [x] Mark this stage as complete.

## Stage 4 — Domain Events and Observability

- [x] Commit previous stage changes.
- [x] Add events under `App\Domain\Forecasting\Events\*`:
  `SolcastRequestAttempted`, `SolcastRequestSucceeded`, `SolcastRequestSkipped`, `SolcastRateLimited`,
  `SolcastAllowanceReset`.
- [x] Emit events from the service during transitions (attempt, skip reason, success, 429/backoff, reset).
- [x] Add listeners/logging hooks (structured logs) and minimal tests for event dispatch (using Laravel events fakes).
- [x] Quality Gate: run `composer all` and ensure green.
- [x] Mark this stage as complete.

## Stage 5 — Application Layer (Commands + Handlers)

- [x] Commit previous stage changes.
- [x] Add Commands:
  - [x] `App\Application\Commands\Forecasting\RequestSolcastForecast` (DTO; includes `force` flag).
  - [x] `App\Application\Commands\Forecasting\RequestSolcastActual` (DTO; includes `force` flag).
- [x] Add Handlers under `...\Handlers\` for each command to:
  - [x] Resolve `SolcastAllowanceService`.
  - [x] Call `checkAndLock()` with endpoint; if not allowed, emit skipped event and return ActionResult.
  - [x] Call existing domain action (`ForecastAction` or `ActualForecastAction`).
  - [x] Based on response (success/HTTP code), call `recordSuccess` or `recordFailure`.
- [x] Map Command => Handler in `App\Providers\AppServiceProvider` (CommandBus mappings).
- [x] Unit tests for handlers (happy path, cap reached, min interval, backoff active, 429).
- [x] Quality Gate: run `composer all` and ensure green.
- [x] Mark this stage as complete.

## Stage 6 — Queries (Read Side)

- [x] Commit previous stage changes.
- [x] Add `App\Application\Queries\Forecasting\SolcastAllowanceStatusQuery` returning `AllowanceStatus`.
- [x] Add `App\Application\Queries\Forecasting\NextEligibleTimesQuery` to compute next eligible timestamps per endpoint.
- [x] Unit tests for query logic using seeded state rows.
- [x] Quality Gate: run `composer all` and ensure green.
- [x] Mark this stage as complete.

## Stage 7 — Filament/UX Integration

- [x] Commit previous stage changes (if any).
- [x] Update Forecast/Actual Filament widgets to dispatch commands rather than directly invoking actions.
- [x] Remove legacy widget-side backoff/interval checks (e.g., `updated_at < now()->subHours(...)`). Policy must be
  centralized in `SolcastAllowanceService` and enforced via the Command/Handler path. The widgets should not block
  execution based on local heuristics.
- [x] Add a small Filament card on the Forecast page showing remaining allowance, next reset, and backoff status.
- [x] Feature tests for UI flows: dispatch command, assert notifications/messages surface policy outcomes.
- [x] Quality Gate: run `composer all` and ensure green.
- [x] Mark this stage as complete.

## Stage 8 — Configuration and .env

- [x] Commit previous stage changes.
- [x] Add environment variables with defaults in `config` and `.env.example`:
  `SOLCAST_DAILY_CAP`, `SOLCAST_FORECAST_MIN_INTERVAL`, `SOLCAST_ACTUAL_MIN_INTERVAL`, `SOLCAST_429_BACKOFF`,
  `SOLCAST_RESET_TZ`.
- [x] Document configuration in `README.md` (brief) and reference the story doc for details.
- [x] Unit test config parsing in the service (e.g., ISO-8601 duration parsing).
- [x] Quality Gate: run `composer all` and ensure green.
- [x] Mark this stage as complete.

## Stage 9 — Integration Tests (End‑to‑End slices)

- [x] Commit previous stage changes.
- [x] Write Feature tests that simulate sequences over time: multiple tries within min interval; just before/after daily
  reset; 429 backoff blocking subsequent attempts; success increments; combined cap enforcement across endpoints.
- [x] Use `Carbon::setTestNow()` and in-memory SQLite per test guidelines.
- [x] Quality Gate: run `composer all` and ensure green.

## Stage 10 — Observability + Admin Surface

- [x] `git` commit any changes from previous stage(s).
- [x] Ensure events produce structured logs at suitable levels (`info`/`warning`).
- [x] Add optional log table writes if `solcast_allowance_logs` is enabled; add pruning command or policy.
- [x] Add an admin page section (Filament) for operational status (read-only view from `AllowanceStatusQuery`).
- [x] Quality Gate: run `composer all` and ensure green.

## Stage 11 — Concurrency/Locking Verification

- [x] `git` commit any changes from previous stage(s).
- [x] Add a test ensuring `lockForUpdate()` path prevents double increments under concurrent attempts (simulate with
  interleaved/sequential race using cap=1 and immediate double reservation attempts in a shared SQLite DB).
- [x] Review transaction boundaries to avoid holding locks during external API calls; use reservation pattern (attempt
  recorded, commit, perform call, then finalize success/failure in a new short transaction).
- [x] Quality Gate: run `composer all` and ensure green.

## Stage 12 — Documentation and Ops Notes

- [x] `git` commit any changes from previous stage(s).
- [x] Update `docs/solcast-api-allowance.md` if any deviations/clarifications were made during implementation.
- [x] Add troubleshooting section (e.g., clock skew, daylight savings in non‑UTC TZs, backoff override policy).
- [x] Quality Gate: run `composer all` and ensure green.

## Stage 13 — Performance Validation (K6 Medium) and Maintenance Log

- [x] Prepare dataset and ensure app is running locally (see `docs/performance-testing.md`).
    - Confirmed: k6 binary is installed. App is running locally. APP_URL=https://solar-dev.test is correct
- [x] Execute Medium cadence twice (clean runs):
  ```bash
  PERF_DATASET_SIZE=medium php artisan migrate:fresh --seed --seeder=PerformanceSeeder
  APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/run-all.sh
  APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/run-all.sh
  ```
- [x] Compare against baseline; ensure tolerances per policy are met. If not, open remediation tasks.
- [x] Update `docs/k6-medium-maintenance.md` with a new row capturing date, gate, runs, and outcome.
- [x] Quality Gate: run `composer all` and ensure green.
- [x] `git add` and `git commit` any changes from this and previous stage(s).

## Stage 14 — Rollout and Clean‑up

- [x] Re‑run full quality suite.
- [x] Rebase/merge and resolve conflicts; final review.
- [x] Ensure `docs/tasks.md` reflects progress (optional cross‑link to this task file).
- [x] Prepare a PR including this checklist; ensure CI is green.

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

### Policy Clarifications (single source of truth)

- All rate/allowance decisions are made by `App\Domain\Forecasting\Services\SolcastAllowanceService` and surfaced via
  Command Handlers. UI widgets and Domain Actions must not implement their own backoff/min-interval checks. `--force`
  only bypasses min-interval checks at the Handler level and never bypasses daily cap or active backoff.

## Code review — Follow-ups Addressed

This section captures concrete tasks derived from the code review notes and aligns them with the plan.

### Charts

- [x] Remove legacy backoff logic from `app/Filament/Widgets/SolcastActualChart.php` and
  `app/Filament/Widgets/SolcastForecastChart.php` (e.g., time-based `updated_at` checks). Rely solely on dispatching
  `RequestSolcastActual`/`RequestSolcastForecast` via the Command Bus. The Handler will consult
  `SolcastAllowanceService` and decide whether to execute or skip. Optionally display informational messages based on
  `AllowanceDecision`/Handler result. Tests: update feature tests to assert no client-side blocking and correct
  messaging when skipped.

### Commands (Artisan)

- [x] Update `app/Console/Commands/Forecast.php` to use the Command Bus instead of calling Actions directly. Add a
  `--force` flag that bypasses only the min-interval check. Ensure output communicates whether the request executed,
  was skipped due to cap/backoff/min-interval, or failed. Add unit/feature tests for the command covering force and
  non-force scenarios.
- [x] README: Document available artisan commands and flags (including `app:forecast` with `--force`), under a new
  "CLI/Artisan Commands" section.

### Domain Actions

- [x] Remove legacy backoff checks from
  `app/Domain/Forecasting/Actions/ForecastAction.php` and
  `app/Domain/Forecasting/Actions/ActualForecastAction.php`. Actions should perform the external call logic only.
  Access control and rate/allowance policy is enforced strictly by the Command Handlers and
  `SolcastAllowanceService`. Add/update unit tests to ensure Actions no longer throw on local time heuristics.

### Acceptance Criteria Additions

- [x] Legacy per-UI/per-Action backoff checks removed; policy centralized in `SolcastAllowanceService` via Handlers.
- [x] `app:forecast` uses Command Bus and supports `--force` (min-interval only). README documents commands and flags.

## Code review

I'm reviewing the code for the detailed plan `docs/solcast-api-allowance-task.md`.

### Charts

The two charts `SolcastActualChart` and `SolcastForecastChart` are correctly calling the new Command bus for
RequestSolcastActual and RequestSolcastForecast. They still have the old backoff check code:

e.g. Actual forecast chart:

```php
$lastUpdate = ActualForecast::query()
    ->latest('period_end')
    ->first();

if (is_null($lastUpdate) || $lastUpdate->updated_at < now()->subHours(self::UPDATE_FREQUENCY_HOURS)) {
    $this->updateSolcast();
}
```

Are these backoffs still required? Should the charts check the new command bus instead? Or `AllowanceDecision`?

### Commands

The artisan command `Forecast` ('php artisan app:forecast') is still calling the Actions directly, can you update the
artisan command to use the new command bus? The command should also have a 'force' flag to bypass the backoff check.

The README.md file does not mention the available artisan commands, can you add all artisan commands to the README.md
file?

Do the Actions `app/Domain/Forecasting/Actions/ForecastAction.php` and
`app/Domain/Forecasting/Actions/ActualForecastAction.php` still need the old backoff check code:

e.g. Forecast action:

```php
$lastForecast = Forecast::latest('updated_at')
    ->first('updated_at');

throw_if(
    !empty($lastForecast) && $lastForecast->updated_at >= now()->subHours(4),
    sprintf(
        'Last updated within 4 hours, try again in %s to avoid rate limiting',
        $lastForecast->updated_at->addHours(4)->diffForHumans()
    )
);
```

Is this backoff still required? Should the Actions check the new command bus instead? Or `AllowanceDecision`? Or has the
command bus already checked this?

### Cap increment

Can you check the logic for incrementing the cap. I tried several times to run the console command, all were
unsuccessful, however the cap was reached. The
`solcast.skipped {"endpoint":"forecast","reason":"daily_cap_reached",...` the cap count should only be incremented when
a successful response was received. If a 429 response is received the cap should be set to the max (6). If an
unsuccessful response was received, this should not affect the cap, e.g. if the network was down, time out, or API key
not set.

#### Cap increment fixed

- [x] Identified why the daily cap was being consumed despite unsuccessful requests:
  `SolcastAllowanceService::checkAndLock()` increments the daily `count` up-front (reservation). Because
  `recordFailure()` did not revert the reservation, any non‑429 failure (e.g., API key missing, timeouts, network) would
  still permanently consume allowance. This matches the behavior you observed in the logs where repeated unsuccessful
  runs led to `solcast.skipped {"reason":"daily_cap_reached"}`.

### Exception handling — Decision and plan updates

Policy and architecture decisions:

- Keep Actions focused on performing the external call. They may throw domain-specific exceptions; they should not
  format user-facing messages nor modify allowance state. Handlers own policy and state transitions.
- Introduce explicit domain exceptions under `App\Domain\Forecasting\Exceptions\*` to represent failure modes in a
  uniform way:
    - `MissingApiKeyException` (configuration error; treat as 400/401 semantic, no backoff, no cap consumption)
    - `RateLimitedException` (HTTP 429; triggers global backoff)
    - `ClientErrorException` (other 4xx)
    - `ServerErrorException` (5xx)
    - `TransportException` (network/timeouts)
    - `UnexpectedResponseException` (schema/parse errors)
- Command Handlers must fully catch these exceptions and convert them into:
    - `recordSuccess()` on success.
    - `recordFailure($status)` with the mapped HTTP-like status for failures. Mapping and side effects in the service:
        - 429 → start/global backoff; reservation counts toward cap per policy; emit `SolcastRateLimited`.
        - Non-429 failures (config, 4xx, 5xx, transport, unexpected) → do not consume daily cap; do not start backoff;
          emit `SolcastRequestSkipped` with reason and details. Ensure the service releases any pre-reservation so cap
          is unchanged. This aligns with the "Cap increment fixed" note above.
- Handlers return a structured result DTO (or array) with fields: `executed` (bool), `status` (int|null), `message` (
  string), and `reason` (enum/string) for UI/CLI surfaces. No exceptions should escape beyond the Handler in normal
  flows.
- The Artisan command (`app:forecast`) and Filament widgets use the Handler’s result to present messages; they should
  not need try/catch for domain exceptions in steady state.

Plan updates to earlier stages:

- Stage 5 — Application Layer (Commands + Handlers)
    - [ ] Add the `App\Domain\Forecasting\Exceptions\*` classes listed above with minimal constructors and accessors.
    - [ ] Update `ForecastAction` and `ActualForecastAction` to throw these exceptions instead of generic ones where
      applicable (e.g., missing API key, 429 handling, HTTP status buckets, transport issues).
    - [ ] Update both Handlers to wrap Action invocation in `try/catch` blocks mapping exceptions to `recordFailure()`
      as described, and producing a structured result. Ensure 429 triggers `recordFailure(429)`; other failures call
      `recordFailure($status)` that does not consume cap.
    - [ ] Unit tests: add tests per exception type asserting the correct service calls, events, and returned result
      contents.
    - [ ] Quality Gate: run `composer all` and ensure green.

- Stage 7 — Filament/UX Integration and Commands
    - [ ] Artisan `app:forecast`: consume the Handler’s structured result and output user-friendly messages. Exit codes:
      `0` success; `2` policy skipped (min-interval/cap/backoff); `3` external failure (4xx/5xx/transport); `4`
      configuration error (missing API key). Ensure `--force` bypasses only min-interval.
    - [ ] Widgets: display informational notices based on the Handler result; no exception handling required in the
      widgets.
    - [ ] Feature tests: cover CLI exit codes and messages for each exception mapping; widget flows show correct notices
      without crashes.

Policy clarifications (append to single source of truth):

- Actions may throw domain exceptions; Handlers must always catch and translate to policy state and user-visible
  results.
- Only successful requests and 429 responses affect the daily cap (per reservation policy and cap-increment fix). All
  other failures leave the cap unchanged and do not start backoff.

### Exception handling

Resolved. See the section "Exception handling — Decision and plan updates" above for the adopted approach, mappings, and
concrete plan changes (Stage 5 and Stage 7 updates). In short: Actions may throw domain exceptions; Handlers catch and
translate to allowance state transitions and structured results; CLI/UI consume results without try/catch in steady
state.
