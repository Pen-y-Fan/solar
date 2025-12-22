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

        $estimatedBatteryRequired = $estimatePV - $this->consumption;

        $import = 0;
        $export = 0;
        $charge = 0;

        if ($this->charging) {
            // Let's charge using inexpensive rate electricity

            // we are charging so negative $estimatedBatteryRequired means we charge the battery
            // the import cost is the chargeAmount + $estimatedBatteryRequired
            // if the battery reaches MAX the export will cut in chargeAmount + PV generated over the max
            $maxChargeAmount = min(self::BATTERY_MAX_CHARGE_PER_HALF_HOUR, self::BATTERY_MAX - $battery);
            $charge = $estimatedBatteryRequired > 0 ? $maxChargeAmount - $estimatedBatteryRequired : $maxChargeAmount;
            $battery += self::BATTERY_MAX_CHARGE_PER_HALF_HOUR;
            $import = $this->consumption;

            // We need to charge the battery from grid
            // The PV may be supplying some charge
            // if the battery is over the battery max and PV is more than demand ($estimatedBatteryRequired > 0)
            // we may be exporting, but only if the $estimatedBatteryRequired > $maxChargeAmount, otherwise we are
            // importing the difference
            if ($battery > self::BATTERY_MAX) {
                if ($estimatedBatteryRequired > 0 && $estimatedBatteryRequired > $maxChargeAmount) {
                    // battery has reached max, we are exporting excess PV
                    // e.g. battery was 4.3 we charged to 4.4 and PC is 0.5, consumption is 0.3
                    // the charge amount is 0.7 we used 0.2 from excess PV
                    $export = $estimatedBatteryRequired - $maxChargeAmount;
                }

                // reset the battery to max
                $battery = self::BATTERY_MAX;
            }
        } else {
            // We are not charging so use the battery then sort out the import or export
            $battery += $estimatedBatteryRequired;

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
        return (int) ($battery * 100 / self::BATTERY_MAX);
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
