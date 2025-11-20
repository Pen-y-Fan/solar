<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

use App\Domain\Forecasting\Models\SolcastAllowanceState;
use Carbon\CarbonImmutable;

/**
 * Snapshot of the current allowance status for Solcast requests.
 */
final class AllowanceStatus
{
    /**
     * @param int $cap Daily combined cap across both endpoints
     * @param int $count Current combined attempt count for the day
     * @param CarbonImmutable|null $resetAt When the next reset occurs
     * @param CarbonImmutable|null $backoffUntil When global backoff ends (if active)
     * @param CarbonImmutable|null $lastAttemptForecast Last attempt timestamp for forecast endpoint
     * @param CarbonImmutable|null $lastAttemptActual Last attempt timestamp for actual endpoint
     * @param CarbonImmutable|null $lastSuccessForecast Last success timestamp for forecast endpoint
     * @param CarbonImmutable|null $lastSuccessActual Last success timestamp for actual endpoint
     */
    public function __construct(
        public readonly int $cap,
        public readonly int $count,
        public readonly ?CarbonImmutable $resetAt = null,
        public readonly ?CarbonImmutable $backoffUntil = null,
        public readonly ?CarbonImmutable $lastAttemptForecast = null,
        public readonly ?CarbonImmutable $lastAttemptActual = null,
        public readonly ?CarbonImmutable $lastSuccessForecast = null,
        public readonly ?CarbonImmutable $lastSuccessActual = null,
    ) {
        if ($cap < 0) {
            throw new \InvalidArgumentException('cap must be >= 0');
        }
        if ($count < 0) {
            throw new \InvalidArgumentException('count must be >= 0');
        }
    }

    public function remainingBudget(): int
    {
        $remaining = $this->cap - $this->count;
        return $remaining > 0 ? $remaining : 0;
    }

    public function isBackoffActive(CarbonImmutable $now): bool
    {
        return $this->backoffUntil !== null && $now->lessThan($this->backoffUntil);
    }

    /**
     * Factory from the singleton model row and provided cap.
     */
    public static function fromModel(SolcastAllowanceState $state, int $cap): self
    {
        return new self(
            cap: $cap,
            count: (int) $state->count,
            resetAt: self::toImmutableNullable($state->reset_at),
            backoffUntil: self::toImmutableNullable($state->backoff_until),
            lastAttemptForecast: self::toImmutableNullable($state->last_attempt_at_forecast),
            lastAttemptActual: self::toImmutableNullable($state->last_attempt_at_actual),
            lastSuccessForecast: self::toImmutableNullable($state->last_success_at_forecast),
            lastSuccessActual: self::toImmutableNullable($state->last_success_at_actual),
        );
    }

    private static function toImmutableNullable(null|string|\DateTimeInterface $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof CarbonImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance(\Carbon\Carbon::instance($value));
        }
        return new CarbonImmutable($value);
    }
}
