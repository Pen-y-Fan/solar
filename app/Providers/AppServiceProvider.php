<?php

namespace App\Providers;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Bus\SimpleCommandBus;
use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Application\Commands\Strategy\GenerateStrategyCommandHandler;
use App\Application\Commands\Strategy\CalculateBatteryCommand;
use App\Application\Commands\Strategy\CalculateBatteryCommandHandler;
use App\Application\Commands\Strategy\CopyConsumptionWeekAgoCommand;
use App\Application\Commands\Strategy\CopyConsumptionWeekAgoCommandHandler;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Application\Commands\Energy\ImportAgileRatesCommandHandler;
use App\Application\Commands\Energy\ExportAgileRatesCommand;
use App\Application\Commands\Energy\ExportAgileRatesCommandHandler;
use App\Application\Commands\Energy\SyncOctopusAccountCommand;
use App\Application\Commands\Energy\SyncOctopusAccountCommandHandler;
use App\Application\Commands\Forecasting\RefreshForecastsCommand;
use App\Application\Commands\Forecasting\RefreshForecastsCommandHandler;
use App\Application\Commands\Strategy\RecalculateStrategyCostsCommand;
use App\Application\Commands\Strategy\RecalculateStrategyCostsCommandHandler;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CommandBus::class, function ($app) {
            $bus = new SimpleCommandBus($app);
            $bus->register(GenerateStrategyCommand::class, GenerateStrategyCommandHandler::class);
            $bus->register(ImportAgileRatesCommand::class, ImportAgileRatesCommandHandler::class);
            $bus->register(ExportAgileRatesCommand::class, ExportAgileRatesCommandHandler::class);
            $bus->register(SyncOctopusAccountCommand::class, SyncOctopusAccountCommandHandler::class);
            $bus->register(CalculateBatteryCommand::class, CalculateBatteryCommandHandler::class);
            $bus->register(CopyConsumptionWeekAgoCommand::class, CopyConsumptionWeekAgoCommandHandler::class);
            $bus->register(RefreshForecastsCommand::class, RefreshForecastsCommandHandler::class);
            $bus->register(RecalculateStrategyCostsCommand::class, RecalculateStrategyCostsCommandHandler::class);
            return $bus;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
