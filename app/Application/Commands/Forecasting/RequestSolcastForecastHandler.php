<?php

declare(strict_types=1);

namespace App\Application\Commands\Forecasting;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Forecasting\Actions\ForecastAction;
use App\Domain\Forecasting\Exceptions\ClientErrorException;
use App\Domain\Forecasting\Exceptions\MissingApiKeyException;
use App\Domain\Forecasting\Exceptions\RateLimitedException;
use App\Domain\Forecasting\Exceptions\ServerErrorException;
use App\Domain\Forecasting\Exceptions\TransportException;
use App\Domain\Forecasting\Exceptions\UnexpectedResponseException;
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
        try {
            $result = $this->action->execute();
            if ($result->isSuccess()) {
                $this->allowance->recordSuccess($endpoint);
                return $result;
            }

            // Unexpected non-exception failure path (legacy) → treat as external failure 500
            $message = $result->getMessage() ?? 'Unknown failure';
            $status = str_contains(strtolower($message), 'rate limit') || str_contains($message, '429') ? 429 : 500;
            $this->allowance->recordFailure($endpoint, $status);
            return ActionResult::failure($message, 'external_failure');
        } catch (RateLimitedException $e) {
            $this->allowance->recordFailure($endpoint, 429);
            return ActionResult::failure($e->getMessage(), 'external_failure');
        } catch (MissingApiKeyException $e) {
            $this->allowance->recordFailure($endpoint, 400);
            return ActionResult::failure($e->getMessage(), 'config_error');
        } catch (ClientErrorException $e) {
            $status = $e->getCode() > 0 ? $e->getCode() : 400;
            $this->allowance->recordFailure($endpoint, $status);
            return ActionResult::failure($e->getMessage(), 'external_failure');
        } catch (ServerErrorException $e) {
            $status = $e->getCode() > 0 ? $e->getCode() : 500;
            $this->allowance->recordFailure($endpoint, $status);
            return ActionResult::failure($e->getMessage(), 'external_failure');
        } catch (TransportException $e) {
            $this->allowance->recordFailure($endpoint, 503);
            return ActionResult::failure($e->getMessage(), 'external_failure');
        } catch (UnexpectedResponseException $e) {
            $this->allowance->recordFailure($endpoint, 502);
            return ActionResult::failure($e->getMessage(), 'external_failure');
        } catch (\Throwable $e) {
            Log::warning('Unhandled exception during forecast action', ['exception' => $e->getMessage()]);
            $this->allowance->recordFailure($endpoint, 500);
            return ActionResult::failure('Unexpected error during Solcast forecast', 'external_failure');
        }
    }
}
