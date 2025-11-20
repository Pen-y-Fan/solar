<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Services;

use App\Domain\Forecasting\Models\SolcastAllowanceState;
use App\Domain\Forecasting\ValueObjects\AllowanceDecision;
use App\Domain\Forecasting\ValueObjects\AllowanceStatus;
use App\Domain\Forecasting\ValueObjects\Endpoint;
use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use App\Domain\Forecasting\Events\SolcastRequestAttempted;
use App\Domain\Forecasting\Events\SolcastRequestSkipped;
use App\Domain\Forecasting\Events\SolcastRequestSucceeded;
use App\Domain\Forecasting\Events\SolcastRateLimited;
use Carbon\CarbonImmutable;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Enforces Solcast API allowance policy with atomic DB locking and reservation pattern.
 *
 * Reservation pattern:
 *  - checkAndLock() evaluates policy under row lock and, if allowed, records an attempt reservation
 *    by incrementing the daily count and setting last_attempt for the endpoint.
 *  - External API call happens after commit.
 *  - recordSuccess()/recordFailure() finalize metadata (success timestamps, backoff on 429) in short transactions.
 */
final class SolcastAllowanceService implements SolcastAllowanceContract
{
    private int $dailyCap;
    private CarbonInterval $forecastMinInterval;
    private CarbonInterval $actualMinInterval;
    private CarbonInterval $backoffDuration;
    private string $resetTz;

    public function __construct(
        ?int $dailyCap = null,
        ?string $forecastMinInterval = null,
        ?string $actualMinInterval = null,
        ?string $backoffDuration = null,
        ?string $resetTz = null,
    ) {
        // Defaults match docs/solcast-api-allowance.md; Stage 8 will move to config.
        $this->dailyCap = $dailyCap ?? (int) config('solcast.allowance.daily_cap', 6);
        $this->forecastMinInterval = self::parseInterval(
            $forecastMinInterval ?? (string) config('solcast.allowance.forecast_min_interval', 'PT4H')
        );
        $this->actualMinInterval = self::parseInterval(
            $actualMinInterval ?? (string) config('solcast.allowance.actual_min_interval', 'PT8H')
        );
        $this->backoffDuration = self::parseInterval(
            $backoffDuration ?? (string) config('solcast.allowance.backoff_429', 'PT8H')
        );
        $this->resetTz = $resetTz ?? (string) config('solcast.allowance.reset_tz', 'UTC');
    }

    /**
     * Evaluate policy and, if allowed, reserve an attempt under lock.
     */
    public function checkAndLock(Endpoint $endpoint, bool $forceMinInterval = false): AllowanceDecision
    {
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($endpoint, $forceMinInterval, $now): AllowanceDecision {
            // Ensure/reset state for now (inside tx) then lock the singleton row
            SolcastAllowanceState::ensureForNow($now, $this->resetTz);

            /** @var SolcastAllowanceState|null $state */
            $state = SolcastAllowanceState::query()->lockForUpdate()->first();
            if ($state === null) {
                // Create if missing (first run)
                $state = SolcastAllowanceState::query()->create([
                    'day_key' => SolcastAllowanceState::dayKeyFor($now, $this->resetTz),
                    'count' => 0,
                    'reset_at' => SolcastAllowanceState::nextResetAtFor($now, $this->resetTz),
                ]);
                $state->refresh();
            }

            // Backoff check (after daily reset handling above)
            if ($state->isBackoffActive($now)) {
                $next = CarbonImmutable::parse((string) $state->backoff_until);
                $decision = AllowanceDecision::deny('backoff_active', $next);
                Event::dispatch(new SolcastRequestSkipped(
                    endpoint: $endpoint,
                    reason: $decision->reason,
                    nextEligibleAt: $decision->nextEligibleAt,
                    at: $now,
                ));
                return $decision;
            }

            // Daily cap
            if ((int) $state->count >= $this->dailyCap) {
                $resetAt = $state->reset_at !== null
                    ? CarbonImmutable::parse((string) $state->reset_at)
                    : null;
                $decision = AllowanceDecision::deny('daily_cap_reached', $resetAt);
                Event::dispatch(new SolcastRequestSkipped(
                    endpoint: $endpoint,
                    reason: $decision->reason,
                    nextEligibleAt: $decision->nextEligibleAt,
                    at: $now,
                ));
                return $decision;
            }

            // Min-interval per endpoint (unless forced)
            if (!$forceMinInterval) {
                if ($endpoint === Endpoint::FORECAST && $state->last_attempt_at_forecast !== null) {
                    $next = CarbonImmutable::parse((string) $state->last_attempt_at_forecast)
                        ->add($this->forecastMinInterval);
                    if ($now->lessThan($next)) {
                        $decision = AllowanceDecision::deny('under_min_interval', $next);
                        Event::dispatch(new SolcastRequestSkipped(
                            endpoint: $endpoint,
                            reason: $decision->reason,
                            nextEligibleAt: $decision->nextEligibleAt,
                            at: $now,
                        ));
                        return $decision;
                    }
                }
                if ($endpoint === Endpoint::ACTUAL && $state->last_attempt_at_actual !== null) {
                    $next = CarbonImmutable::parse((string) $state->last_attempt_at_actual)
                        ->add($this->actualMinInterval);
                    if ($now->lessThan($next)) {
                        $decision = AllowanceDecision::deny('under_min_interval', $next);
                        Event::dispatch(new SolcastRequestSkipped(
                            endpoint: $endpoint,
                            reason: $decision->reason,
                            nextEligibleAt: $decision->nextEligibleAt,
                            at: $now,
                        ));
                        return $decision;
                    }
                }
            }

            // Reserve: increment count and set last_attempt for endpoint
            $state->count = (int) $state->count + 1;
            if ($endpoint === Endpoint::FORECAST) {
                $state->last_attempt_at_forecast = Carbon::instance($now->toDateTime());
            } else {
                $state->last_attempt_at_actual = Carbon::instance($now->toDateTime());
            }
            $state->save();

            // Emit attempted after reservation commit point inside transaction
            Event::dispatch(new SolcastRequestAttempted($endpoint, $now));

            return AllowanceDecision::allow('reserved');
        });
    }

    /**
     * Finalize a successful attempt by recording last success timestamp.
     */
    public function recordSuccess(Endpoint $endpoint): void
    {
        $now = CarbonImmutable::now();
        DB::transaction(function () use ($endpoint, $now): void {
            // lock row
            /** @var SolcastAllowanceState|null $state */
            $state = SolcastAllowanceState::query()->lockForUpdate()->first();
            if ($state === null) {
                return; // nothing to do
            }
            if ($endpoint === Endpoint::FORECAST) {
                $state->last_success_at_forecast = Carbon::instance($now->toDateTime());
            } else {
                $state->last_success_at_actual = Carbon::instance($now->toDateTime());
            }
            $state->save();
        });

        Event::dispatch(new SolcastRequestSucceeded($endpoint, $now));
    }

    /**
     * Finalize a failed attempt; on 429 mark a global backoff window.
     */
    public function recordFailure(Endpoint $endpoint, int $status): void
    {
        $now = CarbonImmutable::now();
        $backoffUntil = null;
        DB::transaction(function () use ($status, $now, &$backoffUntil): void {
            /** @var SolcastAllowanceState|null $state */
            $state = SolcastAllowanceState::query()->lockForUpdate()->first();
            if ($state === null) {
                return;
            }
            if ($status === 429) {
                $backoffUntil = $now->add($this->backoffDuration);
                $state->backoff_until = Carbon::instance($backoffUntil->toDateTime());
            }
            $state->save();
        });

        if ($status === 429 && $backoffUntil !== null) {
            Event::dispatch(new SolcastRateLimited($endpoint, $status, $backoffUntil, CarbonImmutable::now()));
        }
    }

    /**
     * Current status snapshot.
     */
    public function currentStatus(): AllowanceStatus
    {
        $now = CarbonImmutable::now();
        // Ensure/reset and fetch current row
        $state = DB::transaction(function () use ($now): SolcastAllowanceState {
            $row = SolcastAllowanceState::ensureForNow($now, $this->resetTz);
            return $row->refresh();
        });

        return AllowanceStatus::fromModel($state, $this->dailyCap);
    }

    private static function parseInterval(string $value): CarbonInterval
    {
        // Accept ISO8601 (e.g., PT4H) or human (e.g., 4 hours)
        $interval = CarbonInterval::make($value);
        if ($interval === null) {
            throw new \InvalidArgumentException("Invalid duration: {$value}");
        }
        return $interval;
    }
}
