<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy\Models;

use App\Domain\Energy\Models\Inverter;
use App\Domain\Energy\ValueObjects\BatteryStateOfCharge;
use App\Domain\Energy\ValueObjects\EnergyFlow;
use PHPUnit\Framework\TestCase;

final class InverterModelTest extends TestCase
{
    public function testEnergyFlowAccessorReturnsZerosWhenFieldsNull(): void
    {
        // Arrange
        $model = new Inverter();
        // Ensure attributes are null
        $this->assertNull($model->yield);
        $this->assertNull($model->to_grid);
        $this->assertNull($model->from_grid);
        $this->assertNull($model->consumption);

        // Act
        $vo = $model->energyFlow; // getEnergyFlowAttribute

        // Assert
        $this->assertInstanceOf(EnergyFlow::class, $vo);
        $this->assertSame(0.0, $vo->yield);
        $this->assertSame(0.0, $vo->toGrid);
        $this->assertSame(0.0, $vo->fromGrid);
        $this->assertSame(0.0, $vo->consumption);
    }

    public function testBatteryStateOfChargeAccessorReturnsNullWhenBatterySocNull(): void
    {
        // Arrange & Act
        $model = new Inverter();

        // Assert
        $this->assertNull($model->battery_soc);
        $this->assertNull($model->batteryStateOfCharge); // accessor returns null
    }

    public function testBatteryStateOfChargeMutatorSetsScalarAndAccessorReturnsVO(): void
    {
        // Arrange
        $model = new Inverter();
        $soc = new BatteryStateOfCharge(85);

        // Act
        $model->setBatteryStateOfChargeAttribute($soc);
        $this->assertSame(85, $model->battery_soc);

        // Assert
        $vo = $model->batteryStateOfCharge;
        $this->assertInstanceOf(BatteryStateOfCharge::class, $vo);
        $this->assertSame(85, $vo->percentage);
    }

    public function testBatteryStateOfChargeMutatorHandlesNull(): void
    {
        // Arrange
        $model = new Inverter();
        $model->setBatteryStateOfChargeAttribute(new BatteryStateOfCharge(15));
        $this->assertSame(15, $model->battery_soc);

        // Act
        $model->setBatteryStateOfChargeAttribute(null);

        // Assert
        $this->assertNull($model->battery_soc);
        $this->assertNull($model->batteryStateOfCharge);
    }
}
