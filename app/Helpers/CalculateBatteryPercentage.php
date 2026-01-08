<?php

namespace App\Helpers;

class CalculateBatteryPercentage
{
    private const BATTERY_MIN = 0.4;

    private const BATTERY_MAX = 4.0;

    private const BATTERY_MAX_CHARGE_PER_HALF_HOUR = 1.0;

    private bool $charging = false;

    private float $consumption = 0.0;

    private int $batteryPercentage = 10;

    private float $estimatePV = 0.0;

    public function calculate(): array
    {
        $battery = $this->calculateFromBatteryPercentageTokWh();

        // Estimate KWh is per hour, we are calculating per 1/2 hour.
        $estimatePV = $this->estimatePV / 2;

        $powerDifference = $estimatePV - $this->consumption;

        $import = 0;
        $export = 0;
        $charge = 0;

        if ($this->charging) {
            // We are charging, so no battery will be used for consumption.
            // 1. use any $estimatePV for consumption
            // 2. use any remaining excess PV for charging
            // 3. export any remaining excess PV
            $requiredBatteryCharge = min(self::BATTERY_MAX_CHARGE_PER_HALF_HOUR, self::BATTERY_MAX - $battery);
            $battery += $requiredBatteryCharge;

            // Import does not include battery ($charge), this is a separate calculation
            $import = max(0, $this->consumption - $estimatePV);
            $excessPv = max(0, $powerDifference);

            // We need to charge the battery from Pv first
            if ($excessPv > 0) {
                $charge = max(0, $requiredBatteryCharge - $excessPv);
                $excessPv = max(0, $excessPv - $requiredBatteryCharge);
            } else {
                $charge = $requiredBatteryCharge;
            }
            // if we still have excess PV after consumption and charging, it can export
            if ($excessPv > 0) {
                $export = $excessPv;
            }
        } else {
            // We are not charging so use the battery, then sort out the import or export, in reality we will use excess
            // PV, then battery, if available, import the difference, or export the excess.
            $battery += $powerDifference;

            if ($battery < self::BATTERY_MIN) {
                $import = self::BATTERY_MIN - $battery;
                $battery = self::BATTERY_MIN;
            }

            if ($battery > self::BATTERY_MAX) {
                $export = $battery - self::BATTERY_MAX;
                $battery = self::BATTERY_MAX;
            }
        }

        $this->batteryPercentage = $this->convertFromKhhToBatteryPercentage($battery);

        return [$this->batteryPercentage, $charge, $import, $export];
    }

    private function calculateFromBatteryPercentageTokWh(): float
    {
        return $this->batteryPercentage * self::BATTERY_MAX / 100;
    }

    private function convertFromKhhToBatteryPercentage(float $battery): int
    {
        return (int)($battery * 100 / self::BATTERY_MAX);
    }

    public function consumption(float $consumption): CalculateBatteryPercentage
    {
        $this->consumption = $consumption;

        return $this;
    }

    public function startBatteryPercentage(int $batteryPercentage): CalculateBatteryPercentage
    {
        $this->batteryPercentage = $batteryPercentage;

        return $this;
    }

    public function isCharging(bool $charging): CalculateBatteryPercentage
    {
        $this->charging = $charging;

        return $this;
    }

    public function estimatePVkWh(float $estimatePV): CalculateBatteryPercentage
    {
        $this->estimatePV = $estimatePV;

        return $this;
    }
}
