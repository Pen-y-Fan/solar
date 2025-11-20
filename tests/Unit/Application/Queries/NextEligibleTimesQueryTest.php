<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Queries;

use App\Application\Queries\Forecasting\NextEligibleTimesQuery;
use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use App\Domain\Forecasting\ValueObjects\AllowanceStatus;
use Carbon\CarbonImmutable;
use Mockery as m;
use Tests\TestCase;

final class NextEligibleTimesQueryTest extends TestCase
{
    public function testBackoffActiveBlocksBothUntilBackoff(): void
    {
        $now = CarbonImmutable::parse('2025-01-01T10:00:00Z');
        $backoffUntil = $now->addHours(3);

        $status = new AllowanceStatus(
            cap: 6,
            count: 1,
            resetAt: $now->endOfDay(),
            backoffUntil: $backoffUntil,
        );

        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('currentStatus')->once()->andReturn($status);

        $query = new NextEligibleTimesQuery($svc);
        $result = $query->run($now);

        $this->assertSame($backoffUntil->toIso8601String(), $result['forecast']?->toIso8601String());
        $this->assertSame($backoffUntil->toIso8601String(), $result['actual']?->toIso8601String());
    }

    public function testCapReachedReturnsResetAtForBoth(): void
    {
        $now = CarbonImmutable::parse('2025-01-01T10:00:00Z');
        $resetAt = $now->endOfDay();

        $status = new AllowanceStatus(
            cap: 3,
            count: 3,
            resetAt: $resetAt,
            backoffUntil: null,
        );

        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('currentStatus')->once()->andReturn($status);

        $query = new NextEligibleTimesQuery($svc);
        $result = $query->run($now);

        $this->assertSame($resetAt->toIso8601String(), $result['forecast']?->toIso8601String());
        $this->assertSame($resetAt->toIso8601String(), $result['actual']?->toIso8601String());
    }

    public function testMinIntervalsComputeNextTimesAndClampToReset(): void
    {
        // Configure short intervals for test clarity
        config()->set('solcast.allowance.forecast_min_interval', 'PT2H');
        config()->set('solcast.allowance.actual_min_interval', 'PT1H');

        $now = CarbonImmutable::parse('2025-01-01T10:00:00Z');
        $lastForecast = $now->subHour(); // forecast needs +2h => next at 11:00
        $lastActual = $now->subMinutes(30); // actual needs +1h => next at 10:30 -> clamp to now (>= now)
        $resetAt = $now->addHours(1)->addMinutes(30); // 11:30

        $status = new AllowanceStatus(
            cap: 10,
            count: 1,
            resetAt: $resetAt,
            backoffUntil: null,
            lastAttemptForecast: $lastForecast,
            lastAttemptActual: $lastActual,
        );

        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('currentStatus')->once()->andReturn($status);

        $query = new NextEligibleTimesQuery($svc);
        $result = $query->run($now);

        $this->assertSame('2025-01-01T11:00:00+00:00', $result['forecast']?->toIso8601String());
        $this->assertSame('2025-01-01T10:30:00+00:00', $result['actual']?->toIso8601String());

        // If we move reset earlier than computed time, it should clamp to reset
        config()->set('solcast.allowance.forecast_min_interval', 'PT5H'); // would push forecast to 14:00
        $status2 = new AllowanceStatus(
            cap: 10,
            count: 1,
            resetAt: $now->addHour(), // 11:00
            backoffUntil: null,
            lastAttemptForecast: $now, // +5h => 15:00 > reset 11:00 => clamp to reset
            lastAttemptActual: $now->subHours(2), // +1h => 09:00 -> clamp to now
        );
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('currentStatus')->once()->andReturn($status2);
        $result2 = $query->run($now);
        $this->assertSame('2025-01-01T11:00:00+00:00', $result2['forecast']?->toIso8601String());
        $this->assertSame('2025-01-01T10:00:00+00:00', $result2['actual']?->toIso8601String());
    }
}
