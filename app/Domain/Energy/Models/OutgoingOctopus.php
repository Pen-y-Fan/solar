<?php

namespace App\Domain\Energy\Models;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Current Outgoing Octopus export cost
 */
class OutgoingOctopus
{
    // Export cost from 1-March-2026 is 12p
    public const float EXPORT_COST = 12.0;

    // The export rate between 8 July 2025 and before 1-March-2026 is 15p
    public const float EXPORT_COST_TO_MAR_2026 = 15.0;

    public static function getRate(CarbonInterface $date, ?float $modelValue = null): float
    {
        $firstMarch2026 = Carbon::createFromFormat('Y-m-d', '2026-03-01', 'UTC')->startOfDay();
        $eighthJuly2025 = Carbon::createFromFormat('Y-m-d', '2025-07-08', 'UTC')->startOfDay();

        if ($date->isAfter($firstMarch2026) || $date->equalTo($firstMarch2026)) {
            return self::EXPORT_COST;
        }

        if ($date->isAfter($eighthJuly2025) || $date->equalTo($eighthJuly2025)) {
            return self::EXPORT_COST_TO_MAR_2026;
        }

        return $modelValue ?? 0.0;
    }
}
