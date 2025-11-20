<?php

declare(strict_types=1);

namespace App\Application\Commands\Forecasting;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Forecasting\Actions\ForecastAction;
use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use App\Domain\Forecasting\ValueObjects\Endpoint;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;

final class RequestSolcastForecastHandler implements CommandHandler
{
    public function __construct(
        private readonly SolcastAllowanceContract $allowance,
        private readonly ForecastAction $action,
    ) {
    }

    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof RequestSolcastForecast);

        $endpoint = Endpoint::FORECAST;
        $decision = $this->allowance->checkAndLock($endpoint, $command->force);
        if (! $decision->isAllowed()) {
            $msg = match ($decision->reason) {
                'backoff_active' => 'Solcast request skipped: backoff active',
                'daily_cap_reached' => 'Solcast request skipped: daily cap reached',
                'under_min_interval' => 'Solcast request skipped: under minimum interval',
                default => 'Solcast request skipped',
            };
            return ActionResult::failure($msg, 'skipped');
        }

        // Reservation granted; perform the action outside of the lock, then finalize
        $result = $this->action->execute();
        if ($result->isSuccess()) {
            $this->allowance->recordSuccess($endpoint);
            return $result;
        }

        // Failure path: detect 429 heuristic and record failure appropriately
        $message = (string) ($result->getMessage() ?? 'Unknown failure');
        $status = str_contains(strtolower($message), 'rate limit') || str_contains($message, '429') ? 429 : 500;
        try {
            $this->allowance->recordFailure($endpoint, $status);
        } catch (\Throwable $e) {
            Log::warning('Failed to record Solcast allowance failure', [
                'endpoint' => $endpoint->value,
                'status' => $status,
                'exception' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
