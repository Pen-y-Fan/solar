# Solcast API Allowance

This document describes the design and implementation of a budget controller for Solcast API requests.

## Problem statement (expanded)

- Solcast API enforces a hard daily allowance of 6 calls across the endpoints we use. Exceeding the allowance returns HTTP
  429 and may lead to temporary ban, for the day.
- The application triggers both forecast and actual forecast fetches via the console command `app:forecast` and via
  Filament widgets (`SolcastForecastChart`, `SolcastActualChart`). Current simple hourly throttling does not correctly
  respect the global daily allowance, leading to occasional 429s.
- There is no unified, atomic, cross-process budget controller.

## Goals

- Enforce a combined daily budget of 6 calls across both endpoints with atomicity.
- Enforce per-endpoint minimum intervals:
    - forecast: not more often than every 4 hours
    - actual: not more often than every 8 hours
- If a 429 is returned:
    - immediately mark a backoff for 8 hours where no further requests are attempted
- Ensure only one Solcast API request occurs at a time (mutex across processes and endpoints)
- Reset allowance daily at a well-defined boundary (see Timezone)
- Provide observability (events/logs, admin display) and operator control (configurable thresholds)

## Non-goals

- Changing upstream API behavior
- Persisting raw Solcast responses (beyond existing storage) — only metadata for allowance states

## Assumptions and clarifications (answered)

- Timezone for daily reset: Confirmed with Solcast support that they reset daily at midnight UTC. Use
  `SOLCAST_RESET_TZ=UTC` and compute reset windows accordingly.
- Storage: Use the database as the single source of truth for counters, timestamps, and backoff. We will not use Redis
  in this phase.
- Triggering: All triggers should be handled by the same command handler and policy. Filament widgets are the primary
  triggers and should dispatch commands instead of calling domain actions directly. The console command will support a
  `--force` flag to override the min-interval policy (but never the daily cap or backoff).

## Policy details (precedence and flow)

1. If a global backoff is active (e.g., due to 429), block all requests; return an informative ActionResult and emit an
   event.
2. If the day changed (as per configured reset timezone), reset counters and backoff.
3. Enforce mutual exclusion via a cache/Redis lock so only one in-flight request to Solcast is active at any time.
4. If combined daily count >= daily cap (default 6), block and emit event.
5. For the specific endpoint being requested (forecast or actual), check its minimum interval since last success or last
   attempt (choose: last attempt is safer to avoid hammering after failures; we’ll use last attempt).
6. If under the min-interval, skip and emit event.
7. Attempt request. If 429:
    - increment the daily counter (counts as an attempt)
    - set global backoff for 8 hours (configurable)
    - record last error and emit event
8. If non-429 error: increment attempt counter; optional backoff jittered short cooldown (e.g., 5–15 minutes) to reduce
   flapping; emit event.
9. If success: increment daily counter, record last success timestamps and data freshness.

## Data model (database)

Persist allowance state in the database. Use a single-row table for the current day’s state, plus an optional log table
for auditing.

- Table: `solcast_allowance_states`
  - `id` (PK, always `1` for the singleton row)
  - `day_key` (string `YYYYMMDD` in `SOLCAST_RESET_TZ`)
  - `count` (int, total attempts for the day; successes and failures both count)
  - `last_attempt_at_forecast` (timestamp, nullable)
  - `last_attempt_at_actual` (timestamp, nullable)
  - `last_success_at_forecast` (timestamp, nullable)
  - `last_success_at_actual` (timestamp, nullable)
  - `backoff_until` (timestamp, nullable)
  - `reset_at` (timestamp, computed boundary for the current day in `SOLCAST_RESET_TZ`)
  - Timestamps

- Optional table: `solcast_allowance_logs`
  - `id` (PK)
  - `happened_at` (timestamp)
  - `endpoint` (enum: `forecast`, `actual`)
  - `action` (enum: `attempted`, `succeeded`, `skipped`, `rate_limited`)
  - `reason` (nullable string; e.g., `under_min_interval`, `cap_reached`, `backoff_active`)
  - `status_code` (nullable int)
  - `day_key` (string)
  - `count_at_time` (int)
  - Timestamps

State resets when `now() >= reset_at`. On reset, create/refresh the singleton row for the new `day_key` and null the
per-day fields and `backoff_until`.

## Configuration

Add environment variables (with sane defaults):

- `SOLCAST_DAILY_CAP=6`
- `SOLCAST_FORECAST_MIN_INTERVAL=PT4H` (ISO-8601 duration)
- `SOLCAST_ACTUAL_MIN_INTERVAL=PT8H`
- `SOLCAST_429_BACKOFF=PT8H`
- `SOLCAST_RESET_TZ=UTC`

## Concurrency

Because storage is the database, enforce mutual exclusion and atomicity with DB transactions and row-level locks. Two
recommended options:

1) Singleton-row locking in `solcast_allowance_states`:
   - Begin a transaction.
   - Fetch the singleton row with `FOR UPDATE` (Eloquent: `->lockForUpdate()`).
   - Evaluate policy (reset-if-needed, backoff, cap, min-interval) against the locked state.
   - If allowed, update attempt fields (`count`, `last_attempt_at_*`) and commit, then perform the external call.
   - After the call, in a new short transaction, record success/failure and possibly set `backoff_until`.

2) Dedicated mutex table `solcast_allowance_mutex` (single row), locked via `FOR UPDATE` prior to reading/updating the
   state row. This keeps the state row hot-updates separate if desired.

Note: Relying on the cache’s lock mechanism is not required here. Database-level row locks suffice and fit the chosen
storage.

## Observability

- Emit domain events, using Filament notifications, for: attempt, success, skipped (min interval), limit reached, 429
  backoff started, day reset.
- On the Forecast page, add to the existing cards, another small Filament card to show remaining allowance, time to
  reset, and backoff status.
- Log structured messages at `info`/`warning` levels.

## Alternatives considered

- Only increasing interval: Doesn’t reliably fit a daily combined cap; still risks 429 due to different triggers and
  user actions.
- Redis-based counters and locks: Operationally simpler for atomic increments and mutexes, but we chose database to
  align with current environment and simplify infrastructure. The DB approach is robust with proper transactions and
  row locks, at the cost of slightly more application code.

---

## Files/classes to add (CQRS-aligned)

Below is a proposed structure using your existing conventions (`app/Application`, `app/Domain`, Filament widgets, etc.).

## Commands (Application layer)

- `app/Application/Commands/Forecasting/RequestSolcastForecast.php`
    - DTO with no logic; optionally includes a `force` flag to override min-interval (but never exceeds daily cap).
- `app/Application/Commands/Forecasting/RequestSolcastActual.php`
    - Same pattern as above.
- Handlers:
    - `app/Application/Commands/Forecasting/Handlers/RequestSolcastForecastHandler.php`
    - `app/Application/Commands/Forecasting/Handlers/RequestSolcastActualHandler.php`
    - Responsibilities:
        - Resolve `SolcastAllowanceService`
        - Acquire global lock
        - Check policy (backoff, day reset, cap, min-interval)
        - Call existing domain actions `ForecastAction` or `ActualForecastAction`
        - Update counters and timestamps based on result
        - Dispatch domain events and return an `ActionResult`

Map both Command => Handler in `App\Providers\AppServiceProvider` under CommandBus mappings.

## Domain Actions (existing)

- `App\Domain\Forecasting\Actions\ForecastAction` — already exists
- `App\Domain\Forecasting\Actions\ActualForecastAction` — already exists
- Consider small refactors to accept an injected API client that can surface HTTP status codes to detect 429 reliably.

## Domain Services (new)

- `app/Domain/Forecasting/Services/SolcastAllowanceService.php`
    - Encapsulates all allowance policy, timing, atomic updates, and DB lock handling
    - Public API examples:
        - `checkAndLock(Endpoint $endpoint): AllowanceDecision` (mutates state only for lock acquisition)
        - `recordSuccess(Endpoint $endpoint): void`
        - `recordFailure(Endpoint $endpoint, int $status): void`
        - `currentStatus(): AllowanceStatus`
    - Uses `Carbon` with `SOLCAST_RESET_TZ` and database transactions/locks
- Supporting value objects:
    - `app/Domain/Forecasting/ValueObjects/Endpoint.php` (enum: `FORECAST`, `ACTUAL`)
    - `app/Domain/Forecasting/ValueObjects/AllowanceStatus.php` (budget left, backoff until, last attempt/success per
      endpoint, resetAt)
    - `app/Domain/Forecasting/ValueObjects/AllowanceDecision.php` (allowed: bool, reason: enum/string)

## Queries (Application layer)

- `app/Application/Queries/Forecasting/SolcastAllowanceStatusQuery.php`
    - Returns `AllowanceStatus` for UI and operational insights
- `app/Application/Queries/Forecasting/NextEligibleTimesQuery.php`
    - Computes next eligible timestamps for each endpoint based on current status

## Events (Domain)

- `app/Domain/Forecasting/Events/SolcastRequestAttempted.php`
- `app/Domain/Forecasting/Events/SolcastRequestSucceeded.php`
- `app/Domain/Forecasting/Events/SolcastRequestSkipped.php` (reason: under-interval, cap-reached, backoff-active)
- `app/Domain/Forecasting/Events/SolcastRateLimited.php` (429, backoff started)
- `app/Domain/Forecasting/Events/SolcastAllowanceReset.php`

Events can be used for logging, metrics, or future automations.

## Models

Required for DB storage:

- `app/Domain/Forecasting/Models/SolcastAllowanceState.php`
  - Maps to `solcast_allowance_states` (singleton row). Provides helpers to compute `reset_at` and to transition days.

Optional, for auditing:

- `app/Domain/Forecasting/Models/SolcastAllowanceLog.php`
  - Maps to `solcast_allowance_logs` as described above. Include pruning policy.

## Views/Charts (Filament)

- `app/Filament/Widgets/SolcastAllowanceCard.php`
    - Displays:
        - Remaining budget today (6 - count)
        - Next reset time
        - Backoff status and remaining time
        - Last success per endpoint, next eligible times
    - Consumes `SolcastAllowanceStatusQuery`

- Optionally, augment existing widgets (`SolcastForecastChart`, `SolcastActualChart`) to gracefully show a banner when a
  fetch is skipped due to allowance policy and offer “why” info.

## Console Integration

- Update `app/Console/Commands/Forecast.php` handler to dispatch Commands instead of directly invoking Actions:
    - `CommandBus->dispatch(new RequestSolcastForecast())`
    - `CommandBus->dispatch(new RequestSolcastActual())`
    - Respect ActionResult messages so CLI output is informative (success, skipped, backoff, cap, etc.)

## Tests

-- Unit tests
    - `tests/Unit/Domain/Forecasting/SolcastAllowanceServiceTest.php`
        - Day boundary reset logic (various timezones)
        - Global backoff behavior on 429 (8h)
        - Atomic increment and cap enforcement (6)
        - Min interval per endpoint (4h vs 8h)
        - Mutual exclusion via DB row locks (simulate concurrent transactions)
    - `tests/Unit/Application/Commands/RequestSolcastForecastHandlerTest.php`
    - `tests/Unit/Application/Commands/RequestSolcastActualHandlerTest.php`
    - Queries:
        - `tests/Unit/Application/Queries/SolcastAllowanceStatusQueryTest.php`
        - `tests/Unit/Application/Queries/NextEligibleTimesQueryTest.php`

- Feature tests
    - `tests/Feature/Forecasting/SolcastAllowancePolicyFeatureTest.php`
        - Simulate multiple dispatches within a day; assert skip at >6 attempts
        - Simulate 429 response from API client; assert backoff set and respected
        - Assert min-interval skip messages for forecast and actual paths
        - Assert that only one execution occurs when two are triggered concurrently (use parallel jobs or simulated
          overlapping)
    - Filament widget feature tests (if allowance card is added)
        - `tests/Feature/Filament/Widgets/SolcastAllowanceCardTest.php`

- HTTP client tests
    - If Actions use a Solcast API client abstraction, add tests to surface 429 and success, and that handlers interpret
      status codes correctly.

---

## Acceptance criteria

Use these as implementation acceptance tests and DOD (Definition of Done).

1) Combined daily cap enforced

- Given today’s date in `SOLCAST_RESET_TZ` and `SOLCAST_DAILY_CAP=6`
- When the app triggers 6 successful or attempted (including failures) Solcast API calls combined across endpoints
- Then a 7th call attempt on either endpoint is skipped before sending any request
- And the system emits `SolcastRequestSkipped` with reason `cap_reached`
- And CLI/ActionResult conveys “daily cap reached”

2) Per-endpoint minimum interval respected

- Given last attempt for forecast was 3 hours ago
- When requesting forecast
- Then the request is skipped with reason `under_min_interval` and no external call is made
- And when 4 hours have elapsed since the last attempt, the next request is permitted (subject to cap/backoff)
- Similarly for actual with 8-hour min interval

3) 429 handling and global backoff

- Given a request is allowed and sent to Solcast and returns HTTP 429
- Then the system records the attempt (counts toward the daily cap)
- And sets a global backoff of 8 hours (configurable via `SOLCAST_429_BACKOFF`)
- And emits `SolcastRateLimited`
- And subsequent attempts on either endpoint during the backoff window are skipped without sending a request, with clear
  reason `backoff_active`

4) Daily reset

- Given the configured timezone `SOLCAST_RESET_TZ`
- When the date crosses midnight in that timezone
- Then the allowance counters and backoff are reset automatically
- And the system emits `SolcastAllowanceReset`
- And the first request of the new day proceeds (subject to min-interval)

5) Mutual exclusion

- Given two triggers attempt to call Solcast concurrently (e.g., command and widget)
- When both fire at nearly the same time
- Then only one proceeds to make an external call; the other waits briefly for the lock, then re-evaluates policy and
  likely skips due to “already attempted just now”

6) Observability

- When any request is attempted, skipped, succeeds, or triggers backoff
- Then a domain event is emitted and a structured log line is produced
- And `SolcastAllowanceCard` displays budget remaining, backoff status, last success times, and next eligible times

7) Configuration and portability

- Given the `.env` variables are set (cap, intervals, backoff, timezone)
- When deploying to environments with a relational database (MySQL/Postgres/SQLite)
- Then the policy works with atomicity using transactions and row locks; no Redis is required

8) Backward compatibility and UX

- Existing `ForecastAction` and `ActualForecastAction` continue to work behind the new command handlers
- Filament charts show an informational notice when data isn’t refreshed due to policy, not a hard error

9) Tests and quality

- All new unit and feature tests pass using `composer all`
- Static analysis (PHPStan) and code style (PHPCS) pass
- Domain code under `app/Domain` has automated tests as required by project guidelines

10) Scheduling

- Continue to run via a trigger on the Widgets. Do not schedule a cron job.

---

## Suggested implementation notes

- Favor a thin Command Handler + `SolcastAllowanceService` that wraps the domain actions.
- Ensure Actions surface HTTP status codes (429) to the handler; if not available, introduce a Solcast API client
  abstraction returning a result object with status and body.
- Use `CarbonImmutable` for time computations and ISO-8601 durations for config parsing.
- Add small jitter (±1–3 minutes) after resets or success to avoid synchronized bursts in distributed setups.

If you’d like, I can draft the skeletons for the service, commands/handlers, events, queries, and a minimal Filament
card, along with the initial tests — just confirm the timezone assumption and whether Redis is available for atomic
operations.
