<?php

namespace App\Domain\Forecasting\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use App\Domain\Forecasting\Events\SolcastAllowanceReset;

/**
 * Singleton row model storing the current day's Solcast allowance state.
 */
class SolcastAllowanceState extends Model
{
    protected $table = 'solcast_allowance_states';

    protected $fillable = [
        'day_key',
        'count',
        'last_attempt_at_forecast',
        'last_attempt_at_actual',
        'last_success_at_forecast',
        'last_success_at_actual',
        'backoff_until',
        'reset_at',
    ];

    protected $casts = [
        'count' => 'int',
        'last_attempt_at_forecast' => 'datetime',
        'last_attempt_at_actual' => 'datetime',
        'last_success_at_forecast' => 'datetime',
        'last_success_at_actual' => 'datetime',
        'backoff_until' => 'datetime',
        'reset_at' => 'datetime',
    ];

    /**
     * Compute the day key (YYYYMMDD) for a given moment and timezone.
     */
    public static function dayKeyFor(CarbonImmutable $now, string $tz): string
    {
        return $now->setTimezone($tz)->format('Ymd');
    }

    /**
     * Compute the reset boundary timestamp (start of next day in tz).
     */
    public static function nextResetAtFor(CarbonImmutable $now, string $tz): CarbonImmutable
    {
        $localized = $now->setTimezone($tz);
        $startOfNextDay = $localized->startOfDay()->addDay();
        // return in app timezone (UTC by default) to avoid surprises
        $appTz = config('app.timezone', 'UTC');
        return CarbonImmutable::parse($startOfNextDay->toDateTimeString(), $tz)
            ->setTimezone($appTz);
    }

    /**
     * Ensure the singleton state row exists for the current day; reset if needed.
     */
    public static function ensureForNow(CarbonImmutable $now, string $tz): self
    {
        $expectedDayKey = self::dayKeyFor($now, $tz);

        /** @var self|null $row */
        $row = self::query()->first();
        if ($row === null) {
            return self::query()->create([
                'day_key' => $expectedDayKey,
                'count' => 0,
                'reset_at' => self::nextResetAtFor($now, $tz),
            ]);
        }

        // Reset if the stored reset_at has passed or day_key mismatches
        if ($row->reset_at !== null && $now->greaterThanOrEqualTo(CarbonImmutable::parse($row->reset_at))) {
            $row->resetFor($now, $tz);
        } elseif ($row->day_key !== $expectedDayKey) {
            $row->resetFor($now, $tz);
        }

        return $row->refresh();
    }

    /**
     * Reset this row to a fresh state for the provided now/tz.
     */
    public function resetFor(CarbonImmutable $now, string $tz): void
    {
        $this->fill([
            'day_key' => self::dayKeyFor($now, $tz),
            'count' => 0,
            'last_attempt_at_forecast' => null,
            'last_attempt_at_actual' => null,
            'last_success_at_forecast' => null,
            'last_success_at_actual' => null,
            'backoff_until' => null,
            'reset_at' => self::nextResetAtFor($now, $tz),
        ]);
        $this->save();

        // Emit reset domain event for observability
        Event::dispatch(new SolcastAllowanceReset(
            dayKey: (string) $this->day_key,
            resetAt: CarbonImmutable::parse((string) $this->reset_at)
        ));
    }

    /**
     * Whether a global backoff is currently active at the provided time.
     */
    public function isBackoffActive(CarbonImmutable $now): bool
    {
        if ($this->backoff_until === null) {
            return false;
        }
        return $now->lessThan(CarbonImmutable::parse($this->backoff_until));
    }
}
