<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy\ValueObjects;

use App\Domain\Energy\ValueObjects\BatteryStateOfCharge;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BatteryStateOfChargeTest extends TestCase
{
    public function testConstructorEnforcesBounds(): void
    {
        $this->assertSame(0, (new BatteryStateOfCharge(0))->percentage);
        $this->assertSame(100, (new BatteryStateOfCharge(100))->percentage);

        $this->expectException(InvalidArgumentException::class);
        new BatteryStateOfCharge(-1);
    }

    public function testIsFullyChargedAndDischargedHelpers(): void
    {
        $this->assertTrue((new BatteryStateOfCharge(100))->isFullyCharged());
        $this->assertFalse((new BatteryStateOfCharge(50))->isFullyCharged());

        $this->assertTrue((new BatteryStateOfCharge(0))->isFullyDischarged());
        $this->assertFalse((new BatteryStateOfCharge(10))->isFullyDischarged());
    }

    public function testArrayMappingRoundTrip(): void
    {
        $soc = BatteryStateOfCharge::fromArray(['battery_soc' => 42]);
        $this->assertSame(42, $soc->percentage);
        $this->assertSame(['battery_soc' => 42], $soc->toArray());

        $soc2 = BatteryStateOfCharge::fromPercentage(37);
        $this->assertSame(37, $soc2->toArray()['battery_soc']);
    }

    public function testChargeLevelDecimal(): void
    {
        $soc = new BatteryStateOfCharge(25);
        $this->assertSame(0.25, $soc->getChargeLevel());
    }

    public function testFromAndToWattHoursWithCapacityAndClamping(): void
    {
        $capacityWh = 1000; // 1kWh

        $soc50 = BatteryStateOfCharge::fromWattHours(500, $capacityWh);
        $this->assertSame(50, $soc50->percentage);
        $this->assertSame(500, $soc50->toWattHours($capacityWh));

        // Clamp above capacity
        $socOver = BatteryStateOfCharge::fromWattHours(1500, $capacityWh);
        $this->assertSame(100, $socOver->percentage);
        $this->assertSame(1000, $socOver->toWattHours($capacityWh));

        // Clamp below zero
        $socUnder = BatteryStateOfCharge::fromWattHours(-100, $capacityWh);
        $this->assertSame(0, $socUnder->percentage);
        $this->assertSame(0, $socUnder->toWattHours($capacityWh));
    }

    public function testFromAndToWhInvalidCapacityRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BatteryStateOfCharge::fromWattHours(100, 0);
    }

    public function testWithDeltaWattHoursAddAndSubtract(): void
    {
        $capacityWh = 2000; // 2kWh
        $soc = new BatteryStateOfCharge(25); // 500 Wh

        $socAdded = $soc->withDeltaWattHours(1000, $capacityWh); // 1500 Wh => 75%
        $this->assertSame(75, $socAdded->percentage);

        $socSub = $socAdded->withDeltaWattHours(-2000, $capacityWh); // -500 Wh => clamp to 0%
        $this->assertSame(0, $socSub->percentage);

        $socMax = $soc->withDeltaWattHours(5000, $capacityWh); // over capacity => clamp to 100%
        $this->assertSame(100, $socMax->percentage);
    }
}
