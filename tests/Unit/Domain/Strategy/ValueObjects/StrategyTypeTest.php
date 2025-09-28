<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Strategy\ValueObjects;

use App\Domain\Strategy\ValueObjects\StrategyType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StrategyTypeTest extends TestCase
{
    public function testFromArrayAndToArrayRoundTrip(): void
    {
        $data = [
            'strategy1' => StrategyType::CHARGE,
            'strategy2' => StrategyType::DISCHARGE,
            'strategy_manual' => StrategyType::HOLD,
        ];

        $vo = StrategyType::fromArray($data);

        $this->assertSame($data, $vo->toArray());
        $this->assertTrue($vo->hasManualStrategy());
        $this->assertSame(StrategyType::HOLD, $vo->getEffectiveStrategy());
        $this->assertTrue($vo->isHoldStrategy());
        $this->assertFalse($vo->isChargeStrategy());
        $this->assertFalse($vo->isDischargeStrategy());
        $this->assertSame('Hold', $vo->getEffectiveStrategyName());
    }

    public function testEffectiveStrategyFallsBackToStrategy1WhenNoManual(): void
    {
        $vo = new StrategyType(
            strategy1: StrategyType::CHARGE,
            strategy2: StrategyType::DISCHARGE,
            manualStrategy: null
        );

        $this->assertFalse($vo->hasManualStrategy());
        $this->assertSame(StrategyType::CHARGE, $vo->getEffectiveStrategy());
        $this->assertTrue($vo->isChargeStrategy());
        $this->assertSame('Charge', $vo->getEffectiveStrategyName());
    }

    public function testNullsAllowedAndEffectiveNullWhenAllNull(): void
    {
        $vo = new StrategyType();

        $this->assertFalse($vo->hasManualStrategy());
        $this->assertNull($vo->getEffectiveStrategy());
        $this->assertSame('Unknown', $vo->getEffectiveStrategyName());
    }

    public function testInvalidValuesThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StrategyType(strategy1: 999);
    }
}
