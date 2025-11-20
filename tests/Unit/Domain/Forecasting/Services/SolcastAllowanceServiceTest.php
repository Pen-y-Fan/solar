<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting\Services;

use App\Domain\Forecasting\Models\SolcastAllowanceState;
use App\Domain\Forecasting\Services\SolcastAllowanceService;
use App\Domain\Forecasting\ValueObjects\Endpoint;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SolcastAllowanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(null); // reset any prior testNow
    }

    public function testAllowsAndReservesAttempt(): void
    {
        Carbon::setTestNow('2025-11-20 10:00:00');
        $svc = new SolcastAllowanceService(
            dailyCap: 6,
            forecastMinInterval: 'PT4H',
            actualMinInterval: 'PT8H',
            backoffDuration: 'PT8H',
            resetTz: 'UTC'
        );

        $decision = $svc->checkAndLock(Endpoint::FORECAST);
        $this->assertTrue($decision->isAllowed());

        $state = SolcastAllowanceState::query()->firstOrFail();
        $this->assertSame(1, (int) $state->count);
        $this->assertNotNull($state->last_attempt_at_forecast);
        $this->assertNull($state->last_attempt_at_actual);

        // Finalize success
        $svc->recordSuccess(Endpoint::FORECAST);
        $state->refresh();
        $this->assertNotNull($state->last_success_at_forecast);
        $this->assertNull($state->last_success_at_actual);
    }

    public function testMinIntervalDeniesAndForceOverrides(): void
    {
        Carbon::setTestNow('2025-11-20 08:00:00');
        $svc = new SolcastAllowanceService(
            dailyCap: 6,
            forecastMinInterval: 'PT4H',
            actualMinInterval: 'PT8H',
            backoffDuration: 'PT8H',
            resetTz: 'UTC'
        );

        $this->assertTrue($svc->checkAndLock(Endpoint::FORECAST)->isAllowed());

        // Two hours later should be denied (min 4h)
        Carbon::setTestNow('2025-11-20 10:00:00');
        $deny = $svc->checkAndLock(Endpoint::FORECAST);
        $this->assertFalse($deny->isAllowed());
        $this->assertSame('under_min_interval', $deny->reason);
        $this->assertInstanceOf(CarbonImmutable::class, $deny->nextEligibleAt);

        // With force flag it should allow despite min interval
        $allowForced = $svc->checkAndLock(Endpoint::FORECAST, true);
        $this->assertTrue($allowForced->isAllowed());
    }

    public function testDailyCapEnforced(): void
    {
        Carbon::setTestNow('2025-11-20 07:00:00');
        $svc = new SolcastAllowanceService(
            dailyCap: 2,
            forecastMinInterval: 'PT0H',
            actualMinInterval: 'PT0H',
            backoffDuration: 'PT8H',
            resetTz: 'UTC'
        );

        $this->assertTrue($svc->checkAndLock(Endpoint::FORECAST)->isAllowed());
        $this->assertTrue($svc->checkAndLock(Endpoint::ACTUAL)->isAllowed());

        $deny = $svc->checkAndLock(Endpoint::FORECAST);
        $this->assertFalse($deny->isAllowed());
        $this->assertSame('daily_cap_reached', $deny->reason);
    }

    public function testBackoffSetOn429BlocksSubsequent(): void
    {
        Carbon::setTestNow('2025-11-20 09:00:00');
        $svc = new SolcastAllowanceService(
            dailyCap: 6,
            forecastMinInterval: 'PT0H',
            actualMinInterval: 'PT0H',
            backoffDuration: 'PT8H',
            resetTz: 'UTC'
        );

        $this->assertTrue($svc->checkAndLock(Endpoint::ACTUAL)->isAllowed());
        $svc->recordFailure(Endpoint::ACTUAL, 429);

        $deny = $svc->checkAndLock(Endpoint::FORECAST);
        $this->assertFalse($deny->isAllowed());
        $this->assertSame('backoff_active', $deny->reason);

        // Advance beyond backoff window and it should allow again
        Carbon::setTestNow('2025-11-20 17:01:00');
        $allow = $svc->checkAndLock(Endpoint::FORECAST);
        $this->assertTrue($allow->isAllowed());
    }

    public function testResetAcrossDayBoundary(): void
    {
        // 23:59:59 one second before midnight UTC
        Carbon::setTestNow('2025-11-20 23:59:59');
        $svc = new SolcastAllowanceService(
            dailyCap: 1,
            forecastMinInterval: 'PT0H',
            actualMinInterval: 'PT0H',
            backoffDuration: 'PT2H',
            resetTz: 'UTC'
        );

        $this->assertTrue($svc->checkAndLock(Endpoint::FORECAST)->isAllowed());

        // At this point cap reached for the day
        $denySameDay = $svc->checkAndLock(Endpoint::ACTUAL);
        $this->assertFalse($denySameDay->isAllowed());

        // Move to next day; ensure reset allows again
        Carbon::setTestNow('2025-11-21 00:00:01');
        $allowNextDay = $svc->checkAndLock(Endpoint::ACTUAL);
        $this->assertTrue($allowNextDay->isAllowed());
    }
}
