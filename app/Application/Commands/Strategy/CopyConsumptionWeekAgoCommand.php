<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;

/**
 * Command to copy last week's consumption value into the manual consumption field
 * for all Strategy rows within a given date (Europe/London day).
 */
final readonly class CopyConsumptionWeekAgoCommand implements Command
{
    public function __construct(
        /**
         * Day to operate on, e.g. '2025-09-07'. If null, defaults to today in Europe/London.
         */
        public ?string $date = null,
    ) {
    }
}
