<?php

namespace App\Domain\Strategy\DTOs;

use App\Domain\Strategy\Enums\StrategyType;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Support\Collection;

class StrategyCostCalculatorRequest
{
    /**
     * @param Collection<Strategy> $strategies
     * @param int $startBattery
     * @param StrategyType $strategyType Type: manual/strategy1/strategy2 (default ManualStrategy).
     */
    public function __construct(
        public Collection $strategies,
        public int $startBattery,
        public StrategyType $strategyType = StrategyType::ManualStrategy
    ) {
    }
}
