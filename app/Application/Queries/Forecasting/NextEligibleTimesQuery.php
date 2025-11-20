<?php

declare(strict_types=1);

namespace App\Application\Queries\Forecasting;

use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;

/**
 * Computes next eligible timestamps per endpoint under the current policy
 * (respecting global backoff, combined daily cap, per-endpoint min-intervals, and daily reset).
 */
final readonly class NextEligibleTimesQuery
{
    public function __construct(private SolcastAllowanceContract $allowance)
    {
    }

    /**
     * @return array{forecast: CarbonImmutable|null, actual: CarbonImmutable|null}
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $status = $this->allowance->currentStatus();

        $forecastInterval = self::parseInterval((string) config('solcast.allowance.forecast_min_interval', 'PT4H'));
        $actualInterval = self::parseInterval((string) config('solcast.allowance.actual_min_interval', 'PT8H'));

        // If backoff is active, both endpoints are blocked until backoffUntil
        if ($status->isBackoffActive($now)) {
            return [
                'forecast' => $status->backoffUntil,
                'actual' => $status->backoffUntil,
            ];
        }

        // If daily cap reached, next eligible is at resetAt (or null if unknown)
        if ($status->remainingBudget() <= 0) {
            return [
                'forecast' => $status->resetAt,
                'actual' => $status->resetAt,
            ];
        }

        $forecastNext = $status->lastAttemptForecast?->add($forecastInterval);
        $actualNext = $status->lastAttemptActual?->add($actualInterval);

        // Eligible times cannot be in the past; clamp to now
        if ($forecastNext === null || $forecastNext->lessThanOrEqualTo($now)) {
            $forecastNext = $now;
        }
        if ($actualNext === null || $actualNext->lessThanOrEqualTo($now)) {
            $actualNext = $now;
        }

        // Respect the daily reset: if resetAt is sooner than the computed time, use resetAt for clarity
        if ($status->resetAt !== null) {
            if ($forecastNext->greaterThan($status->resetAt)) {
                $forecastNext = $status->resetAt;
            }
            if ($actualNext->greaterThan($status->resetAt)) {
                $actualNext = $status->resetAt;
            }
        }

        return [
            'forecast' => $forecastNext,
            'actual' => $actualNext,
        ];
    }

    private static function parseInterval(string $value): CarbonInterval
    {
        // Support ISO-8601 durations; fallback to minutes if numeric passed.
        if (is_numeric($value)) {
            return CarbonInterval::minutes((int) $value);
        }
        $interval = CarbonInterval::make($value);
        if ($interval instanceof CarbonInterval) {
            return $interval;
        }
        // Default to 0 interval if unparsable
        return CarbonInterval::minutes(0);
    }
}
