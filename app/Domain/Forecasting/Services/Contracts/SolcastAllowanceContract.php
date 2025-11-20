<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Services\Contracts;

use App\Domain\Forecasting\ValueObjects\AllowanceDecision;
use App\Domain\Forecasting\ValueObjects\AllowanceStatus;
use App\Domain\Forecasting\ValueObjects\Endpoint;

/**
 * Abstraction for Solcast allowance policy to enable mocking in tests and decouple handlers.
 */
interface SolcastAllowanceContract
{
    public function checkAndLock(Endpoint $endpoint, bool $forceMinInterval = false): AllowanceDecision;

    public function recordSuccess(Endpoint $endpoint): void;

    public function recordFailure(Endpoint $endpoint, int $status): void;

    public function currentStatus(): AllowanceStatus;
}
