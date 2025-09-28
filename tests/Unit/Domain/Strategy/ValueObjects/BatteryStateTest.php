<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Strategy\ValueObjects;

use App\Domain\Strategy\ValueObjects\BatteryState;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BatteryStateTest extends TestCase
{
    public function testFromArrayAndToArrayRoundTripAndEffectivePercentage(): void
    {
        $data = [
            'battery_percentage1' => 80,
            'battery_charge_amount' => 1.5,
            'battery_percentage_manual' => 60,
        ];

        $vo = BatteryState::fromArray($data);

        $this->assertSame($data, $vo->toArray());
        $this->assertTrue($vo->hasManualPercentage());
        $this->assertSame(60, $vo->getEffectivePercentage());
        $this->assertTrue($vo->isCharging());
        $this->assertFalse($vo->isDischarging());
    }

    public function testDischargeAndFallbackWhenNoManual(): void
    {
        $vo = new BatteryState(percentage: 25, chargeAmount: -0.5, manualPercentage: null);

        $this->assertFalse($vo->hasManualPercentage());
        $this->assertSame(25, $vo->getEffectivePercentage());
        $this->assertTrue($vo->isDischarging());
        $this->assertFalse($vo->isCharging());
    }

    public function testBoundsValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BatteryState(percentage: 101, chargeAmount: 0.0);
    }

    public function testManualBoundsValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BatteryState(percentage: 50, chargeAmount: 0.0, manualPercentage: -1);
    }
}
