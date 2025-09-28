<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Forecasting\RefreshForecastsCommand;
use App\Application\Commands\Forecasting\RefreshForecastsCommandHandler;
use App\Domain\Forecasting\Actions\ActualForecastAction;
use App\Domain\Forecasting\Actions\ForecastAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use Tests\TestCase;

final class RefreshForecastsCommandHandlerTest extends TestCase
{
    public function testSuccessRunsActualThenForecastAndAggregates(): void
    {
        Log::spy();

        /** @var m\MockInterface&ActualForecastAction $actual */
        $actual = m::mock(ActualForecastAction::class);
        /** @var m\MockInterface&ForecastAction $forecast */
        $forecast = m::mock(ForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $actual->shouldReceive('execute')->once()->andReturn(
            ActionResult::success(['records' => 24], 'Actual forecast updated')
        );
        // @phpstan-ignore-next-line mock expectation
        $forecast->shouldReceive('execute')->once()->andReturn(
            ActionResult::success(['records' => 72], 'Forecast updated')
        );

        $handler = new RefreshForecastsCommandHandler($actual, $forecast);
        $result = $handler->handle(new RefreshForecastsCommand(date: now('Europe/London')->format('Y-m-d')));

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Refreshed forecasts (actual: 24, forecast: 72)', $result->getMessage());

        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'RefreshForecastsCommand started' && array_key_exists('date', $context);
        })->once();
        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'RefreshForecastsCommand finished'
                && ($context['success'] ?? null) === true
                && array_key_exists('ms', $context);
        })->once();
    }

    public function testStopsAndFailsIfActualFails(): void
    {
        Log::spy();

        /** @var m\MockInterface&ActualForecastAction $actual */
        $actual = m::mock(ActualForecastAction::class);
        /** @var m\MockInterface&ForecastAction $forecast */
        $forecast = m::mock(ForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $actual->shouldReceive('execute')->once()->andReturn(ActionResult::failure('No API key'));
        // Forecast should not be called in this case
        $forecast->shouldNotReceive('execute');

        $handler = new RefreshForecastsCommandHandler($actual, $forecast);
        $result = $handler->handle(new RefreshForecastsCommand());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Actual forecast failed: No API key', $result->getMessage());
        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'RefreshForecastsCommand actual failed'
                && ($context['message'] ?? null) === 'No API key';
        })->once();
    }

    public function testFailsIfForecastFailsAfterActualSuccess(): void
    {
        Log::spy();

        /** @var m\MockInterface&ActualForecastAction $actual */
        $actual = m::mock(ActualForecastAction::class);
        /** @var m\MockInterface&ForecastAction $forecast */
        $forecast = m::mock(ForecastAction::class);

        // @phpstan-ignore-next-line mock expectation
        $actual->shouldReceive('execute')->once()->andReturn(
            ActionResult::success(['records' => 10], 'Actual forecast updated')
        );
        // @phpstan-ignore-next-line mock expectation
        $forecast->shouldReceive('execute')->once()->andReturn(ActionResult::failure('Rate limit'));

        $handler = new RefreshForecastsCommandHandler($actual, $forecast);
        $result = $handler->handle(new RefreshForecastsCommand());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Forecast failed: Rate limit', $result->getMessage());
        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'RefreshForecastsCommand forecast failed'
                && ($context['message'] ?? null) === 'Rate limit';
        })->once();
    }
}
