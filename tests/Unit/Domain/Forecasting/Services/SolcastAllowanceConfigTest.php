<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting\Services;

use App\Domain\Forecasting\Models\SolcastAllowanceState;
use App\Domain\Forecasting\Services\SolcastAllowanceService;
use App\Domain\Forecasting\ValueObjects\Endpoint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class SolcastAllowanceConfigTest extends TestCase
{
    use DatabaseMigrations;

    public function testServiceReadsConfigAndParsesIso8601Durations(): void
    {
        // Arrange: configure allowance via config() (simulates .env)
        config([
            'solcast.allowance.daily_cap' => 2,
            'solcast.allowance.forecast_min_interval' => 'PT10H',
            'solcast.allowance.actual_min_interval' => 'PT12H',
            'solcast.allowance.backoff_429' => 'PT5H',
            'solcast.allowance.reset_tz' => 'UTC',
        ]);

        $svc = new SolcastAllowanceService(); // reads from config

        // Ensure state exists for now
        SolcastAllowanceState::ensureForNow(now()->toImmutable(), 'UTC');

        // Act 1: First forecast attempt should be allowed and consume 1 count
        $d1 = $svc->checkAndLock(Endpoint::FORECAST);
        $this->assertTrue($d1->isAllowed(), 'First forecast attempt should be allowed');

        // Act 2: Immediate second forecast attempt without force should be blocked by min interval (PT10H)
        $d2 = $svc->checkAndLock(Endpoint::FORECAST);
        $this->assertFalse($d2->isAllowed(), 'Second immediate forecast should be denied by min interval');

        // Act 3: Force flag should bypass min interval, consuming the remaining cap
        $d3 = $svc->checkAndLock(Endpoint::FORECAST, true);
        $this->assertTrue($d3->isAllowed(), 'Forced forecast should bypass min interval');

        // Act 4: Actual endpoint should now be denied by combined daily cap = 2
        $d4 = $svc->checkAndLock(Endpoint::ACTUAL);
        $this->assertFalse($d4->isAllowed(), 'Actual should be denied by daily cap after two reservations');
    }
}
