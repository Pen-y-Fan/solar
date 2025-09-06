<?php

namespace App\Console\Commands;

use App\Domain\Forecasting\Actions\ActualForecastAction;
use App\Domain\Forecasting\Actions\ForecastAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Forecast extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:forecast';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch forecast data from the Solis API and save it to the database';

    /**
     * Execute the console command.
     */
    public function handle(ForecastAction $forecast, ActualForecastAction $actualForecast): void
    {
        try {
            $result = $forecast->execute();
            if ($result->isSuccess()) {
                $this->info('Forecast has been fetched!');
            } else {
                $this->warn('Forecast fetch failed: ' . ($result->getMessage() ?? 'unknown error'));
            }
        } catch (\Throwable $th) {
            Log::error('Error running forecast import action', ['error message' => $th->getMessage()]);
            $this->error('Error running forecast import action:');
            $this->error($th->getMessage());
        }

        try {
            $result = $actualForecast->execute();
            if ($result->isSuccess()) {
                $this->info('Actual forecast has been fetched!');
            } else {
                $this->warn('Actual forecast fetch failed: ' . ($result->getMessage() ?? 'unknown error'));
            }
        } catch (\Throwable $th) {
            Log::error('Error running actual forecast import action', ['error message' => $th->getMessage()]);
            $this->error('Error running actual forecast import action:');
            $this->error($th->getMessage());
        }
    }
}
