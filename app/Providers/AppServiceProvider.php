<?php

namespace App\Providers;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Bus\SimpleCommandBus;
use App\Application\Commands\Energy\ExportAgileRatesCommand;
use App\Application\Commands\Energy\ExportAgileRatesCommandHandler;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Application\Commands\Energy\ImportAgileRatesCommandHandler;
use App\Application\Commands\Energy\SyncOctopusAccountCommand;
use App\Application\Commands\Energy\SyncOctopusAccountCommandHandler;
use App\Application\Commands\Forecasting\RefreshForecastsCommand;
use App\Application\Commands\Forecasting\RefreshForecastsCommandHandler;
use App\Application\Commands\Forecasting\RequestSolcastActual;
use App\Application\Commands\Forecasting\RequestSolcastActualHandler;
use App\Application\Commands\Forecasting\RequestSolcastForecast;
use App\Application\Commands\Forecasting\RequestSolcastForecastHandler;
use App\Application\Commands\Strategy\CalculateBatteryCommand;
use App\Application\Commands\Strategy\CalculateBatteryCommandHandler;
use App\Application\Commands\Strategy\CopyConsumptionWeekAgoCommand;
use App\Application\Commands\Strategy\CopyConsumptionWeekAgoCommandHandler;
use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Application\Commands\Strategy\GenerateStrategyCommandHandler;
use App\Application\Commands\Strategy\GetInverterDayDataCommand;
use App\Application\Commands\Strategy\GetInverterDayDataHandler;
use App\Application\Commands\Strategy\RecalculateStrategyCostsCommand;
use App\Application\Commands\Strategy\RecalculateStrategyCostsCommandHandler;
use App\Domain\Forecasting\Events\SolcastAllowanceReset;
use App\Domain\Forecasting\Events\SolcastRateLimited;
use App\Domain\Forecasting\Events\SolcastRequestAttempted;
use App\Domain\Forecasting\Events\SolcastRequestSkipped;
use App\Domain\Forecasting\Events\SolcastRequestSucceeded;
use App\Domain\Forecasting\Models\SolcastAllowanceLog;
use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use App\Domain\Forecasting\Services\SolcastAllowanceService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Solcast allowance contract to its concrete service
        $this->app->bind(SolcastAllowanceContract::class, SolcastAllowanceService::class);

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
            // Solcast allowance commands
            $bus->register(RequestSolcastForecast::class, RequestSolcastForecastHandler::class);
            $bus->register(RequestSolcastActual::class, RequestSolcastActualHandler::class);
            // Solis commands
            $bus->register(GetInverterDayDataCommand::class, GetInverterDayDataHandler::class);
            return $bus;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lightweight structured logging for domain events (observability)
        Event::listen(SolcastRequestAttempted::class, static function (SolcastRequestAttempted $e): void {
            try {
                Log::info('solcast.attempted', [
                    'endpoint' => $e->endpoint->value,
                    'at' => (string) $e->at,
                ]);
                if ((bool) config('solcast.allowance.log_to_db', false)) {
                    SolcastAllowanceLog::create([
                        'event_type' => 'attempted',
                        'endpoint' => $e->endpoint->value,
                        'payload' => null,
                        'created_at' => $e->at,
                    ]);
                }
            } catch (\Throwable) {
                // no-op
            }
        });
        Event::listen(SolcastRequestSucceeded::class, static function (SolcastRequestSucceeded $e): void {
            try {
                Log::info('solcast.succeeded', [
                    'endpoint' => $e->endpoint->value,
                    'at' => (string) $e->at,
                ]);
                if ((bool) config('solcast.allowance.log_to_db', false)) {
                    SolcastAllowanceLog::create([
                        'event_type' => 'succeeded',
                        'endpoint' => $e->endpoint->value,
                        'payload' => null,
                        'created_at' => $e->at,
                    ]);
                }
            } catch (\Throwable) {
                // no-op
            }
        });
        Event::listen(SolcastRequestSkipped::class, static function (SolcastRequestSkipped $e): void {
            try {
                Log::info('solcast.skipped', [
                    'endpoint' => $e->endpoint->value,
                    'reason' => $e->reason,
                    'nextEligibleAt' => $e->nextEligibleAt?->toIso8601String(),
                    'at' => (string) $e->at,
                ]);
                if ((bool) config('solcast.allowance.log_to_db', false)) {
                    SolcastAllowanceLog::create([
                        'event_type' => 'skipped',
                        'endpoint' => $e->endpoint->value,
                        'reason' => $e->reason,
                        'next_eligible_at' => $e->nextEligibleAt,
                        'payload' => null,
                        'created_at' => $e->at,
                    ]);
                }
            } catch (\Throwable) {
                // no-op
            }
        });
        Event::listen(SolcastRateLimited::class, static function (SolcastRateLimited $e): void {
            try {
                Log::warning('solcast.rate_limited', [
                    'endpoint' => $e->endpoint->value,
                    'status' => $e->status,
                    'backoffUntil' => $e->backoffUntil->toIso8601String(),
                    'at' => (string) $e->at,
                ]);
                if ((bool) config('solcast.allowance.log_to_db', false)) {
                    SolcastAllowanceLog::create([
                        'event_type' => 'rate_limited',
                        'endpoint' => $e->endpoint->value,
                        'status' => $e->status,
                        'backoff_until' => $e->backoffUntil,
                        'payload' => null,
                        'created_at' => $e->at,
                    ]);
                }
            } catch (\Throwable) {
                // no-op
            }
        });
        Event::listen(SolcastAllowanceReset::class, static function (SolcastAllowanceReset $e): void {
            try {
                Log::info('solcast.allowance_reset', [
                    'dayKey' => $e->dayKey,
                    'resetAt' => $e->resetAt->toIso8601String(),
                ]);
                if ((bool) config('solcast.allowance.log_to_db', false)) {
                    SolcastAllowanceLog::create([
                        'event_type' => 'allowance_reset',
                        'day_key' => $e->dayKey,
                        'reset_at' => $e->resetAt,
                        'payload' => null,
                    ]);
                }
            } catch (\Throwable) {
                // no-op
            }
        });

        // Local-only lightweight SQL profiling toggle.
        // Enable by setting PERF_PROFILE=true in .env when APP_ENV=local or testing.
        if (app()->environment(['local', 'testing']) && (bool) config('perf.profile', false)) {
            DB::listen(static function (QueryExecuted $query): void {
                try {
                    Log::debug('sql', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms' => $query->time,
                        'connection' => $query->connectionName,
                    ]);
                } catch (\Throwable $e) {
                    // Swallow any logging errors to avoid impacting app behavior during profiling
                }
            });
        }
    }
}
