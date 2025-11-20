<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Queries;

use App\Application\Queries\Forecasting\SolcastAllowanceStatusQuery;
use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use App\Domain\Forecasting\ValueObjects\AllowanceStatus;
use Carbon\CarbonImmutable;
use Mockery as m;
use Tests\TestCase;

final class SolcastAllowanceStatusQueryTest extends TestCase
{
    public function testReturnsCurrentStatusFromService(): void
    {
        $now = CarbonImmutable::parse('2025-01-01T12:00:00Z');

        $status = new AllowanceStatus(
            cap: 6,
            count: 2,
            resetAt: $now->addDay(),
            backoffUntil: null,
        );

        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('currentStatus')->once()->andReturn($status);

        $query = new SolcastAllowanceStatusQuery($svc);
        $result = $query->run();

        $this->assertSame(6, $result->cap);
        $this->assertSame(2, $result->count);
        $this->assertSame(4, $result->remainingBudget());
        $this->assertFalse($result->isBackoffActive($now));
    }
}
