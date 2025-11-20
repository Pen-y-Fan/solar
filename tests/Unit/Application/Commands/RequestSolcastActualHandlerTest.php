<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Forecasting\RequestSolcastActual;
use App\Application\Commands\Forecasting\RequestSolcastActualHandler;
use App\Domain\Forecasting\Actions\ActualForecastAction;
use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use App\Domain\Forecasting\ValueObjects\AllowanceDecision;
use App\Domain\Forecasting\ValueObjects\Endpoint;
use App\Support\Actions\ActionResult;
use Mockery as m;
use Tests\TestCase;

final class RequestSolcastActualHandlerTest extends TestCase
{
    public function testAllowedSuccessRecordsSuccessAndReturnsResult(): void
    {
        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        /** @var m\MockInterface&ActualForecastAction $action */
        $action = m::mock(ActualForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('checkAndLock')
            ->once()
            ->with(Endpoint::ACTUAL, false)
            ->andReturn(AllowanceDecision::allow('reserved'));
        // @phpstan-ignore-next-line mock expectation
        $action->shouldReceive('execute')
            ->once()
            ->andReturn(ActionResult::success(['records' => 10], 'Actual forecast updated'));
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('recordSuccess')->once()->with(Endpoint::ACTUAL);

        $handler = new RequestSolcastActualHandler($svc, $action);
        $result = $handler->handle(new RequestSolcastActual());

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Actual forecast updated', $result->getMessage());
    }

    public function testDeniedDueToMinIntervalReturnsSkippedFailure(): void
    {
        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        /** @var m\MockInterface&ActualForecastAction $action */
        $action = m::mock(ActualForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('checkAndLock')
            ->once()
            ->with(Endpoint::ACTUAL, false)
            ->andReturn(AllowanceDecision::deny('under_min_interval'));
        // action should not be called
        $action->shouldNotReceive('execute');

        $handler = new RequestSolcastActualHandler($svc, $action);
        $result = $handler->handle(new RequestSolcastActual());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('skipped', $result->getCode());
        $this->assertSame('Solcast request skipped: under minimum interval', $result->getMessage());
    }

    public function testFailureWithRateLimitTriggers429RecordFailure(): void
    {
        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        /** @var m\MockInterface&ActualForecastAction $action */
        $action = m::mock(ActualForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('checkAndLock')
            ->once()
            ->with(Endpoint::ACTUAL, true)
            ->andReturn(AllowanceDecision::allow('reserved'));
        // @phpstan-ignore-next-line mock expectation
        $action->shouldReceive('execute')
            ->once()
            ->andReturn(ActionResult::failure('Rate limit exceeded'));
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('recordFailure')->once()->with(Endpoint::ACTUAL, 429);

        $handler = new RequestSolcastActualHandler($svc, $action);
        $result = $handler->handle(new RequestSolcastActual(force: true));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Rate limit exceeded', $result->getMessage());
    }
}
