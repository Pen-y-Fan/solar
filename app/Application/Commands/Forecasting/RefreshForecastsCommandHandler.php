<?php

declare(strict_types=1);

namespace App\Application\Commands\Forecasting;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Forecasting\Actions\ActualForecastAction;
use App\Domain\Forecasting\Actions\ForecastAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RefreshForecastsCommandHandler implements CommandHandler
{
    public function __construct(
        private readonly ActualForecastAction $actual,
        private readonly ForecastAction $forecast,
    ) {
    }

    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof RefreshForecastsCommand);

        $startedAt = microtime(true);
        Log::info('RefreshForecastsCommand started', ['date' => $command->date]);

        try {
            // These actions each include their own safety checks and are independent writes.
            // We will not wrap them in a single transaction to avoid long-lived external calls
            // inside a DB transaction. We will, however, run them sequentially and stop on first failure.

            $actualResult = $this->actual->execute();
            if (! $actualResult->isSuccess()) {
                Log::warning('RefreshForecastsCommand actual failed', [
                    'message' => $actualResult->getMessage(),
                ]);
                return ActionResult::failure('Actual forecast failed: ' . ($actualResult->getMessage() ?? 'Unknown'));
            }

            $forecastResult = $this->forecast->execute();
            if (! $forecastResult->isSuccess()) {
                Log::warning('RefreshForecastsCommand forecast failed', [
                    'message' => $forecastResult->getMessage(),
                ]);
                return ActionResult::failure('Forecast failed: ' . ($forecastResult->getMessage() ?? 'Unknown'));
            }

            $ms = (int) ((microtime(true) - $startedAt) * 1000);
            Log::info('RefreshForecastsCommand finished', [
                'success' => true,
                'ms' => $ms,
            ]);

            $recordsActual = (int)($actualResult->getData()['records'] ?? 0);
            $recordsForecast = (int)($forecastResult->getData()['records'] ?? 0);
            $message = sprintf('Refreshed forecasts (actual: %d, forecast: %d)', $recordsActual, $recordsForecast);

            return ActionResult::success([
                'actual_records' => $recordsActual,
                'forecast_records' => $recordsForecast,
            ], $message);
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $startedAt) * 1000);
            Log::warning('RefreshForecastsCommand failed', [
                'exception' => $e->getMessage(),
                'ms' => $ms,
            ]);
            return ActionResult::failure('Refresh forecasts failed: ' . $e->getMessage());
        }
    }
}
