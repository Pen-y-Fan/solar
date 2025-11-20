<?php

declare(strict_types=1);

namespace App\Application\Commands\Forecasting;

use App\Application\Commands\Contracts\Command;

/**
 * Command DTO to request a Solcast Forecast call via allowance policy.
 */
final readonly class RequestSolcastForecast implements Command
{
    public function __construct(
        /** When true, bypasses per-endpoint min-interval only (cap/backoff still enforced). */
        public bool $force = false,
    ) {
    }
}
