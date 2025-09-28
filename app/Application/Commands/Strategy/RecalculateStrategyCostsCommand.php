<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;

/**
 * Command to recompute cost-related fields for Strategy rows across a date or date range.
 * Dates are interpreted in Europe/London and converted to UTC for DB queries.
 */
final readonly class RecalculateStrategyCostsCommand implements Command
{
    public function __construct(
        /** Inclusive start day (Y-m-d) in Europe/London. If null and end is null, defaults to today. */
        public ?string $dateFrom = null,
        /** Inclusive end day (Y-m-d) in Europe/London. If null, uses dateFrom. */
        public ?string $dateTo = null,
    ) {
    }
}
