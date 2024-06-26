<?php

namespace App\Console\Commands;

use App\Actions\ActualForecast;
use App\Actions\Forecast as ForecastAction;
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
    protected $description = 'Fetch forecast data';


    /**
     * Execute the console command.
     */
    public function handle(ForecastAction $forecast, ActualForecast $actualForecast): void
    {
        try {
            $forecast->run();
            $this->info('Forecast has been fetched!');
        } catch (\Throwable $th) {
            Log::error('Error running forecast import action', ['error message' => $th->getMessage()]);
            $this->error('Error running forecast import action:');
            $this->error($th->getMessage());
        }

        try {
            $actualForecast->run();
            $this->info('Actual forecast has been fetched!');
        } catch (\Throwable $th) {
            Log::error('Error running actual forecast import action', ['error message' => $th->getMessage()]);
            $this->error('Error running actual forecast import action:');
            $this->error($th->getMessage());
        }
    }
}
