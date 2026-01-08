<?php

namespace Tests\Unit\Helpers;

use App\Helpers\CalculateBatteryPercentage;
use PHPUnit\Framework\TestCase;

class CalculateBatteryPercentageTest extends TestCase
{
    public function testCalculateWhenChargingAndWithinBatteryLimitsExcessPvWillGoToConsumptionAndSomeBatteryCharge()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(25) // 1 kWh
            ->consumption(1.1)
            ->estimatePVkWh(4.0)
            ->isCharging(true);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $calculator->calculate();

        // Pv per 30 min = 2 - 1.1 (consumption) (0 import) = 0.9 remaining - 1.0 battery required (charge 0.1)
        // = 0.0 excess
        $this->assertEquals(50, $batteryPercentage);
        $this->assertEqualsWithDelta(0, $importAmount, 0.01);
        $this->assertEqualsWithDelta(0.1, $chargeAmount, 0.01);
        $this->assertEqualsWithDelta(0, $exportAmount, 0.01);
    }

    public function testCalculateWhenChargingAndWithinBatteryLimitsExcessPvWillGoToConsumptionBatteryAndSomeExport()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(25) // 1 kWh
            ->consumption(0.5)
            ->estimatePVkWh(4.0)
            ->isCharging(true);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $calculator->calculate();

        // Pv per 30 min = 2 - 0.5 (consumption) (0 import) = 1.5 remaining - 1.0 battery required (charge 0)
        // = 0.5 excess
        $this->assertEquals(50, $batteryPercentage);
        $this->assertEqualsWithDelta(0, $importAmount, 0.01);
        $this->assertEqualsWithDelta(0, $chargeAmount, 0.01);
        $this->assertEqualsWithDelta(0.5, $exportAmount, 0.01);
    }

    public function testCalculateWhenChargingWithExcessPvImportIsHighWillCoverConsumptionBatteryAndGoToExport()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(25) // 1 kWh
            ->consumption(0.5)
            ->estimatePVkWh(1.0)
            ->isCharging(true);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $calculator->calculate();

        // Pv per 30 min = 1 - 1 (consumption) (0 import) = 0 remaining - 1.0 battery required (1 charge) = 0 export
        $this->assertEquals(50, $batteryPercentage);
        $this->assertEqualsWithDelta(0, $importAmount, 0.01);
        $this->assertEqualsWithDelta(1, $chargeAmount, 0.01);
        $this->assertEqualsWithDelta(0, $exportAmount, 0.01);
    }

    public function testCalculateWhenChargingAndExceedsBatteryMax()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(95) // 0.2 kWh
            ->consumption(0.5)
            ->estimatePVkWh(2.0)
            ->isCharging(true);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $calculator->calculate();
        // Pv per 30 min = 1 - 0.5 (consumption) (0 import) = 0.5 remaining - 0.2 battery required (0 charge)
        // = 0.3 excess
        $this->assertEquals(100, $batteryPercentage);
        $this->assertEqualsWithDelta(0, $chargeAmount, 0.01);
        $this->assertEqualsWithDelta(0, $importAmount, 0.01);
        $this->assertEqualsWithDelta(0.3, $exportAmount, 0.01);
    }
    public function testCalculateWhenChargingAndEBatteryAlreadyFull()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(100)
            ->consumption(0.5)
            ->estimatePVkWh(2.0)
            ->isCharging(true);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $calculator->calculate();
        // Pv per 30 min = 1 - 0.5 (consumption) (0 import) = 0.5 remaining - 0 battery required (0 charge)
        // = 0.5 excess
        $this->assertEquals(100, $batteryPercentage);
        $this->assertEqualsWithDelta(0, $chargeAmount, 0.01);
        $this->assertEqualsWithDelta(0, $importAmount, 0.01);
        $this->assertEqualsWithDelta(0.5, $exportAmount, 0.01);
    }

    public function testCalculateWithoutChargingAndWithinBatteryLimits()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(50)
            ->consumption(0.5)
            ->estimatePVkWh(1.0)
            ->isCharging(false);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $calculator->calculate();
        // Pv per 30 min = 0.5 - 0.5 (consumption) (0 import) = 0 remaining - 0 battery required (50% battery)
        // = 0 excess
        $this->assertEquals(50, $batteryPercentage);
        $this->assertEqualsWithDelta(0, $importAmount, 0.01);
        $this->assertEqualsWithDelta(0, $chargeAmount, 0.01);
        $this->assertEqualsWithDelta(0, $exportAmount, 0.01);
    }

    public function testCalculateWithoutChargingAndBatteryDropsBelowMin()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(25) // 1 Kwh
            ->consumption(1.5)
            ->estimatePVkWh(0.0)
            ->isCharging(false);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $calculator->calculate();
        // Pv per 30 min = 0 - 1.5 (consumption) = 1.5 required - 0.6 battery available (15% battery) = 0 excess
        $this->assertEquals(10, $batteryPercentage);
        $this->assertEqualsWithDelta(0.9, $importAmount, 0.01);
        $this->assertEqualsWithDelta(0, $chargeAmount, 0.01);
        $this->assertEqualsWithDelta(0, $exportAmount, 0.01);
    }

    public function testCalculateWithoutChargingAndBatteryExceedsMax()
    {
        $calculator = (new CalculateBatteryPercentage())
            ->startBatteryPercentage(90)
            ->consumption(1.0)
            ->estimatePVkWh(4.0)
            ->isCharging(false);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $calculator->calculate();

        $this->assertEquals(100, $batteryPercentage);
        $this->assertEqualsWithDelta(0, $chargeAmount, 0.01);
        $this->assertEqualsWithDelta(0, $importAmount, 0.01);
        $this->assertEqualsWithDelta(0.6, $exportAmount, 0.01);
    }
}
