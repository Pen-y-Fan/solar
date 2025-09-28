<?php

declare(strict_types=1);

namespace App\Application\Commands\Forecasting;

use App\Application\Commands\Contracts\Command;

/**
 * Orchestrates refreshing both Actual and Future forecasts.
 * By default operates on "now" constraints used by the Actions themselves
 * (they internally guard against running more than once per hour).
 */
final readonly class RefreshForecastsCommand implements Command
{
    public function __construct(
        /** Optional hint date (Y-m-d). Actions mostly rely on now() so this is informational for logging. */
        public ?string $date = null,
    ) {
    }
}
