<?php

namespace Tests\Helpers;

use App\Helpers\CalculateBatteryPercentage;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;

class CalculateBatteryPercentageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    public function testCalculateWhenChargingAndWithinBatteryLimits()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(50)
            ->consumption(0.5)
            ->estimatePVkWh(2.0)
            ->isCharging(true);

        $result = $calculator->calculate();

        $this->assertEquals(75, $result);
    }

    public function testCalculateWhenChargingAndExceedsBatteryMax()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(100)
            ->consumption(1.0)
            ->estimatePVkWh(2.0)
            ->isCharging(true);

        $result = $calculator->calculate();

        $this->assertEquals(100, $result);
    }

    public function testCalculateWithoutChargingAndWithinBatteryLimits()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(50)
            ->consumption(0.5)
            ->estimatePVkWh(1.0)
            ->isCharging(false);

        $result = $calculator->calculate();

        $this->assertEquals(50, $result);
    }

    public function testCalculateWithoutChargingAndBatteryDropsBelowMin()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(25)
            ->consumption(1.5)
            ->estimatePVkWh(0.0)
            ->isCharging(false);

        $result = $calculator->calculate();

        $this->assertEquals(10, $result);
    }

    public function testCalculateWithoutChargingAndBatteryExceedsMax()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(90)
            ->consumption(1.0)
            ->estimatePVkWh(4.0)
            ->isCharging(false);

        $result = $calculator->calculate();

        $this->assertEquals(100, $result);
    }
}
