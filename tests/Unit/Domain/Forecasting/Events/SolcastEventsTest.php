<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting\Events;

use App\Domain\Forecasting\Events\SolcastAllowanceReset;
use App\Domain\Forecasting\Events\SolcastRateLimited;
use App\Domain\Forecasting\Events\SolcastRequestAttempted;
use App\Domain\Forecasting\Events\SolcastRequestSkipped;
use App\Domain\Forecasting\Events\SolcastRequestSucceeded;
use App\Domain\Forecasting\Models\SolcastAllowanceState;
use App\Domain\Forecasting\Services\SolcastAllowanceService;
use App\Domain\Forecasting\ValueObjects\Endpoint;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class SolcastEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(null);
    }

    public function testDispatchesAttemptedOnReserve(): void
    {
        Carbon::setTestNow('2025-11-20 10:00:00');
        $svc = new SolcastAllowanceService(dailyCap: 6, forecastMinInterval: 'PT0H', actualMinInterval: 'PT0H');

        Event::fake();

        $svc->checkAndLock(Endpoint::FORECAST);

        Event::assertDispatched(SolcastRequestAttempted::class, function (SolcastRequestAttempted $e): bool {
            return $e->endpoint === Endpoint::FORECAST;
        });
    }

    public function testDispatchesSkippedOnMinInterval(): void
    {
        Carbon::setTestNow('2025-11-20 08:00:00');
        $svc = new SolcastAllowanceService(dailyCap: 6, forecastMinInterval: 'PT4H', actualMinInterval: 'PT0H');

        Event::fake();

        $this->assertTrue($svc->checkAndLock(Endpoint::FORECAST)->isAllowed());
        Carbon::setTestNow('2025-11-20 10:00:00');
        $svc->checkAndLock(Endpoint::FORECAST);

        Event::assertDispatched(SolcastRequestSkipped::class, function (SolcastRequestSkipped $e): bool {
            return $e->endpoint === Endpoint::FORECAST
                && $e->reason === 'under_min_interval'
                && $e->nextEligibleAt !== null;
        });
    }

    public function testDispatchesSkippedOnDailyCap(): void
    {
        Carbon::setTestNow('2025-11-20 07:00:00');
        $svc = new SolcastAllowanceService(dailyCap: 1, forecastMinInterval: 'PT0H', actualMinInterval: 'PT0H');

        Event::fake();

        $this->assertTrue($svc->checkAndLock(Endpoint::FORECAST)->isAllowed());
        $svc->checkAndLock(Endpoint::ACTUAL);

        Event::assertDispatched(SolcastRequestSkipped::class, function (SolcastRequestSkipped $e): bool {
            return $e->reason === 'daily_cap_reached';
        });
    }

    public function testDispatchesSucceededOnRecordSuccess(): void
    {
        Carbon::setTestNow('2025-11-20 09:00:00');
        $svc = new SolcastAllowanceService(dailyCap: 6, forecastMinInterval: 'PT0H', actualMinInterval: 'PT0H');

        Event::fake();

        $this->assertTrue($svc->checkAndLock(Endpoint::ACTUAL)->isAllowed());
        $svc->recordSuccess(Endpoint::ACTUAL);

        Event::assertDispatched(SolcastRequestSucceeded::class, function (SolcastRequestSucceeded $e): bool {
            return $e->endpoint === Endpoint::ACTUAL;
        });
    }

    public function testDispatchesRateLimitedOn429AndSubsequentSkipsDuringBackoff(): void
    {
        Carbon::setTestNow('2025-11-20 09:00:00');
        $svc = new SolcastAllowanceService(
            dailyCap: 6,
            forecastMinInterval: 'PT0H',
            actualMinInterval: 'PT0H',
            backoffDuration: 'PT8H'
        );

        Event::fake();

        $this->assertTrue($svc->checkAndLock(Endpoint::ACTUAL)->isAllowed());
        $svc->recordFailure(Endpoint::ACTUAL, 429);

        Event::assertDispatched(SolcastRateLimited::class, function (SolcastRateLimited $e): bool {
            return $e->endpoint === Endpoint::ACTUAL && $e->status === 429;
        });

        // Now any attempt should be skipped due to backoff
        $svc->checkAndLock(Endpoint::FORECAST);
        Event::assertDispatched(SolcastRequestSkipped::class, function (SolcastRequestSkipped $e): bool {
            return $e->reason === 'backoff_active';
        });
    }

    public function testDispatchesResetOnDayBoundary(): void
    {
        Carbon::setTestNow('2025-11-20 23:59:50');
        Event::fake();

        // Create initial state with reset_at in the past relative to next now
        SolcastAllowanceState::ensureForNow(Carbon::now()->toImmutable(), 'UTC');

        // Advance time beyond stored reset_at
        Carbon::setTestNow('2025-11-21 00:00:10');

        // ensureForNow should trigger reset and emit event
        SolcastAllowanceState::ensureForNow(Carbon::now()->toImmutable(), 'UTC');

        Event::assertDispatched(SolcastAllowanceReset::class);
    }
}
