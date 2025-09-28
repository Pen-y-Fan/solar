<?php

namespace App\Console\Commands;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Forecasting\RefreshForecastsCommand;
use Illuminate\Console\Command;

class RefreshForecastsConsole extends Command
{
    protected $signature = 'forecasts:refresh {--date=}';

    protected $description = 'Refresh actual and future PV forecasts via CommandBus';

    public function handle(CommandBus $bus): int
    {
        $date = $this->option('date');
        $result = $bus->dispatch(new RefreshForecastsCommand(is_string($date) && $date !== '' ? $date : null));

        if ($result->isSuccess()) {
            $this->info($result->getMessage() ?? 'Forecasts refreshed');
            return self::SUCCESS;
        }

        $this->error($result->getMessage() ?? 'Failed to refresh forecasts');
        return self::FAILURE;
    }
}
