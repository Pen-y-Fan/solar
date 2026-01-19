<?php

namespace App\Domain\Strategy\DTOs;

use App\Application\Commands\Strategy\DTOs\BatteryCalculationResult;
use Illuminate\Support\Collection;

class StrategyCostCalculatorResult
{
    /**
     * @param float $totalCost
     * @param int $endBattery
     * @param Collection<BatteryCalculationResult> $batteryResults
     */
    public function __construct(
        public float $totalCost,
        public int $endBattery,
        public Collection $batteryResults,
    ) {
    }
}
