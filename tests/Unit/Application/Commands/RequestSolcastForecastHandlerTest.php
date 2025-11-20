<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Forecasting\RequestSolcastForecast;
use App\Application\Commands\Forecasting\RequestSolcastForecastHandler;
use App\Domain\Forecasting\Actions\ForecastAction;
use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use App\Domain\Forecasting\ValueObjects\AllowanceDecision;
use App\Domain\Forecasting\ValueObjects\Endpoint;
use App\Support\Actions\ActionResult;
use Mockery as m;
use Tests\TestCase;

final class RequestSolcastForecastHandlerTest extends TestCase
{
    public function testAllowedSuccessRecordsSuccessAndReturnsResult(): void
    {
        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        /** @var m\MockInterface&ForecastAction $action */
        $action = m::mock(ForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('checkAndLock')
            ->once()
            ->with(Endpoint::FORECAST, false)
            ->andReturn(AllowanceDecision::allow('reserved'));
        // @phpstan-ignore-next-line mock expectation
        $action->shouldReceive('execute')
            ->once()
            ->andReturn(ActionResult::success(['records' => 10], 'Forecast updated'));
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('recordSuccess')->once()->with(Endpoint::FORECAST);

        $handler = new RequestSolcastForecastHandler($svc, $action);
        $result = $handler->handle(new RequestSolcastForecast());

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Forecast updated', $result->getMessage());
    }

    public function testDeniedDueToBackoffReturnsSkippedFailure(): void
    {
        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        /** @var m\MockInterface&ForecastAction $action */
        $action = m::mock(ForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('checkAndLock')
            ->once()
            ->with(Endpoint::FORECAST, false)
            ->andReturn(AllowanceDecision::deny('backoff_active'));
        // action should not be called
        $action->shouldNotReceive('execute');

        $handler = new RequestSolcastForecastHandler($svc, $action);
        $result = $handler->handle(new RequestSolcastForecast());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('skipped', $result->getCode());
        $this->assertSame('Solcast request skipped: backoff active', $result->getMessage());
    }

    public function testFailureWithRateLimitTriggers429RecordFailure(): void
    {
        /** @var m\MockInterface&SolcastAllowanceContract $svc */
        $svc = m::mock(SolcastAllowanceContract::class);
        /** @var m\MockInterface&ForecastAction $action */
        $action = m::mock(ForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('checkAndLock')
            ->once()
            ->with(Endpoint::FORECAST, true)
            ->andReturn(AllowanceDecision::allow('reserved'));
        // @phpstan-ignore-next-line mock expectation
        $action->shouldReceive('execute')
            ->once()
            ->andReturn(ActionResult::failure('Rate limit exceeded (429)'));
        // @phpstan-ignore-next-line mock expectation
        $svc->shouldReceive('recordFailure')->once()->with(Endpoint::FORECAST, 429);

        $handler = new RequestSolcastForecastHandler($svc, $action);
        $result = $handler->handle(new RequestSolcastForecast(force: true));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Rate limit exceeded (429)', $result->getMessage());
    }
}
