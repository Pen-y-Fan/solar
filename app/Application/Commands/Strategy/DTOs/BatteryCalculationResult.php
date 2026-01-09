<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy\DTOs;

final readonly class BatteryCalculationResult
{
    public function __construct(
        public int $batteryPercentage,
        public float $chargeAmount,
        public float $importAmount,
        public float $exportAmount,
    ) {
    }
}
