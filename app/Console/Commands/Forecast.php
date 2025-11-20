<?php

namespace App\Console\Commands;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Forecasting\RequestSolcastActual;
use App\Application\Commands\Forecasting\RequestSolcastForecast;
use App\Support\Actions\ActionResult;
use Illuminate\Console\Command;

class Forecast extends Command
{
    /**
     * php artisan app:forecast
     * php artisan app:forecast --force
     *
     * Exit codes:
     * `0` success; `2` policy skipped (min-interval/cap/backoff); `3` external failure (4xx/5xx/transport); `4`
     */
    protected $signature = 'app:forecast'
    . ' {--force : Bypass only the per-endpoint min-interval check (daily allowance still enforced)}';

    protected $description = 'Fetch forecast data from the Solcast API and save it to the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = (bool)$this->option('force');
        $bus = app(CommandBus::class);

        $exitCode = 0;

        $actual = $bus->dispatch(new RequestSolcastActual(force: $force));
        $exitCode = max($exitCode, $this->reportResult('actual', $actual));

        $forecast = $bus->dispatch(new RequestSolcastForecast(force: $force));
        return max($exitCode, $this->reportResult('forecast', $forecast));
    }

    private function reportResult(string $endpointLabel, ActionResult $result): int
    {
        if ($result->isSuccess()) {
            $this->info("{$this->getForecastType($endpointLabel)} has been fetched!");
            return 0;
        }

        $code = $result->getCode();
        $msg = $result->getMessage() ?? 'unknown error';

        if ($code === 'skipped') {
            $this->line("{$this->getForecastType($endpointLabel)} request skipped: $msg");
            return 2;
        }

        if ($code === 'config_error') {
            $this->error("{$this->getForecastType($endpointLabel)} configuration error: $msg");
            return 4;
        }

        $this->warn("{$this->getForecastType($endpointLabel)} fetch failed: $msg");
        return 3;
    }

    public function getForecastType(string $endpointLabel): string
    {
        return $endpointLabel === 'actual' ? 'Actual forecast' : 'Forecast';
    }
}
