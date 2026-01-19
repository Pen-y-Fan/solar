<?php

declare(strict_types=1);

namespace App\Domain\Strategy\Services;

use App\Domain\Strategy\DTOs\StrategyCostCalculatorRequest;
use App\Domain\Strategy\DTOs\StrategyCostCalculatorResult;
use App\Domain\Strategy\Enums\StrategyType;
use App\Helpers\CalculateBatteryPercentage;

class StrategyCostCalculator
{
    public function calculateTotalCost(StrategyCostCalculatorRequest $request): StrategyCostCalculatorResult
    {
        $totalCost = 0.0;
        $batteryResults = collect();
        $currentBattery = $request->startBattery;
        $calc = new CalculateBatteryPercentage();
        $calc->startBatteryPercentage($request->startBattery);

        foreach ($request->strategies as $strategy) {
            $chargeStrategy = match ($request->strategyType) {
                StrategyType::Strategy1 => $strategy->strategy1,
                StrategyType::Strategy2 => $strategy->strategy2,
                StrategyType::ManualStrategy => $strategy->strategy_manual ?? false,
            };
            $batteryResult = $calc
                ->isCharging($chargeStrategy)
                ->consumption($strategy->consumption_manual  ?? 0.0)
                ->estimatePVkWh($strategy->forecast->pv_estimate ?? 0.0)
                ->calculate();

            $periodCost = ($batteryResult->importAmount + $batteryResult->chargeAmount)
                * ($strategy->import_value_inc_vat ?? 0.0)
                - $batteryResult->exportAmount * ($strategy->export_value_inc_vat ?? 0.0);
            $totalCost += $periodCost;
            $currentBattery = $batteryResult->batteryPercentage;
            $batteryResults->add($batteryResult);
        }

        return new StrategyCostCalculatorResult($totalCost, $currentBattery, $batteryResults);
    }
}
