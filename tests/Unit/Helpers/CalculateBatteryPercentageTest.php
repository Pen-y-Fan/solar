<?php

namespace Tests\Unit\Helpers;

use App\Helpers\CalculateBatteryPercentage;
use PHPUnit\Framework\TestCase;

class CalculateBatteryPercentageTest extends TestCase
{
    public function test_calculate_when_charging_and_within_battery_limits()
    {
        $calculator = (new CalculateBatteryPercentage)
            ->startBatteryPercentage(50)
            ->consumption(0.5)
            ->estimatePVkWh(2.0)
            ->isCharging(true);

        [$result] = $calculator->calculate();

        $this->assertEquals(75, $result);
    }

    public function test_calculate_when_charging_and_exceeds_battery_max()
    {
        $calculator = (new CalculateBatteryPercentage)
            ->startBatteryPercentage(100)
            ->consumption(1.0)
            ->estimatePVkWh(2.0)
            ->isCharging(true);

        [$result] = $calculator->calculate();

        $this->assertEquals(100, $result);
    }

    public function test_calculate_without_charging_and_within_battery_limits()
    {
        $calculator = (new CalculateBatteryPercentage)
            ->startBatteryPercentage(50)
            ->consumption(0.5)
            ->estimatePVkWh(1.0)
            ->isCharging(false);

        [$result] = $calculator->calculate();

        $this->assertEquals(50, $result);
    }

    public function test_calculate_without_charging_and_battery_drops_below_min()
    {
        $calculator = (new CalculateBatteryPercentage)
            ->startBatteryPercentage(25)
            ->consumption(1.5)
            ->estimatePVkWh(0.0)
            ->isCharging(false);

        [$result] = $calculator->calculate();

        $this->assertEquals(10, $result);
    }

    public function test_calculate_without_charging_and_battery_exceeds_max()
    {
        $calculator = (new CalculateBatteryPercentage)
            ->startBatteryPercentage(90)
            ->consumption(1.0)
            ->estimatePVkWh(4.0)
            ->isCharging(false);

        [$result] = $calculator->calculate();

        $this->assertEquals(100, $result);
    }
}
