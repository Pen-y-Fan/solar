<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;

/**
 * Calculate battery values for strategies in a given day.
 *
 * If $date is null, defaults to today (Europe/London day window).
 */
final class CalculateBatteryCommand implements Command
{
    public function __construct(
        public readonly ?string $date = null,
        public readonly bool $simulate = false,
    ) {
    }
}
