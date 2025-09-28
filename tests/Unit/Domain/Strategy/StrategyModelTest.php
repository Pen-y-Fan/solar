<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Strategy;

use App\Domain\Strategy\Models\Strategy;
use App\Domain\Strategy\ValueObjects\StrategyType;
use PHPUnit\Framework\TestCase;

final class StrategyModelTest extends TestCase
{
    public function testConsumptionDataVoClampsAndRoundTrips(): void
    {
        $model = new Strategy();

        // Directly set raw attributes (simulate DB values including negatives)
        $model->setRawAttributes([
            'consumption_last_week' => -5.0,
            'consumption_average' => 10.5,
            'consumption_manual' => null,
        ], true);

        $vo = $model->getConsumptionDataValueObject();
        // Negative should be clamped to 0.0 by getter logic
        $this->assertEqualsWithDelta(0.0, $vo->lastWeek, 1e-6);
        $this->assertEqualsWithDelta(10.5, $vo->average, 1e-6);
        $this->assertNull($vo->manual);

        // Now use the accessors/mutators (these also clamp inputs)
        $model->consumption_last_week = -3.2; // setter clamps to 0
        $model->consumption_average = 7.7;
        $model->consumption_manual = 0.0;

        $this->assertEqualsWithDelta(0.0, $model->consumption_last_week, 1e-6);
        $this->assertEqualsWithDelta(7.7, $model->consumption_average, 1e-6);
        $this->assertEqualsWithDelta(0.0, $model->consumption_manual, 1e-6);
    }

    public function testBatteryStateVoMapping(): void
    {
        $model = new Strategy();

        $model->setRawAttributes([
            'battery_percentage1' => 80,
            'battery_charge_amount' => 12.5,
            'battery_percentage_manual' => null,
        ], true);

        $vo = $model->getBatteryStateValueObject();
        $this->assertSame(80, $vo->percentage);
        $this->assertEqualsWithDelta(12.5, $vo->chargeAmount, 1e-6);
        $this->assertNull($vo->manualPercentage);

        // Update only manual percentage through mutator and ensure VO updates
        $model->battery_percentage_manual = 55;
        $vo2 = $model->getBatteryStateValueObject();
        $this->assertSame(55, $vo2->manualPercentage);
        $this->assertSame(80, $vo2->percentage);
        $this->assertEqualsWithDelta(12.5, $vo2->chargeAmount, 1e-6);
    }

    public function testStrategyTypeVoBooleanMapping(): void
    {
        $model = new Strategy();

        // strategy1/2 booleans map to StrategyType::CHARGE when true, NONE when false
        $model->setRawAttributes([
            'strategy1' => true,
            'strategy2' => false,
            'strategy_manual' => null,
        ], true);

        $this->assertTrue($model->strategy1); // getter returns boolean
        $this->assertFalse($model->strategy2);
        $this->assertNull($model->strategy_manual);

        $vo = $model->getStrategyTypeValueObject();
        $this->assertSame(StrategyType::CHARGE, $vo->strategy1);
        $this->assertSame(StrategyType::NONE, $vo->strategy2);
        $this->assertNull($vo->manualStrategy);

        // Now flip flags through mutators and set manual flag
        $model->strategy1 = false;
        $model->strategy2 = true;
        $model->strategy_manual = true; // stored as 1 in VO manualStrategy

        $this->assertFalse($model->strategy1);
        $this->assertTrue($model->strategy2);
        $this->assertTrue($model->strategy_manual);

        $vo2 = $model->getStrategyTypeValueObject();
        $this->assertSame(StrategyType::NONE, $vo2->strategy1);
        $this->assertSame(StrategyType::CHARGE, $vo2->strategy2);
        $this->assertSame(1, $vo2->manualStrategy);
    }

    public function testCostDataVoMappingAndHelpers(): void
    {
        $model = new Strategy();

        $model->setRawAttributes([
            'import_value_inc_vat' => 12.34,
            'export_value_inc_vat' => 2.34,
            'consumption_average_cost' => 0.99,
            'consumption_last_week_cost' => 1.99,
        ], true);

        $vo = $model->getCostDataValueObject();
        $this->assertEqualsWithDelta(12.34, $vo->importValueIncVat, 1e-6);
        $this->assertEqualsWithDelta(2.34, $vo->exportValueIncVat, 1e-6);
        $this->assertEqualsWithDelta(0.99, $vo->consumptionAverageCost, 1e-6);
        $this->assertEqualsWithDelta(1.99, $vo->consumptionLastWeekCost, 1e-6);

        // Accessors should reflect VO
        $this->assertEqualsWithDelta(12.34, $model->import_value_inc_vat, 1e-6);
        $this->assertEqualsWithDelta(2.34, $model->export_value_inc_vat, 1e-6);

        // Mutate one value and ensure VO is updated
        $model->import_value_inc_vat = 20.00;
        $this->assertEqualsWithDelta(20.00, $model->import_value_inc_vat, 1e-6);

        $vo2 = $model->getCostDataValueObject();
        $this->assertEqualsWithDelta(20.00, $vo2->importValueIncVat, 1e-6);
        $this->assertEqualsWithDelta(2.34, $vo2->exportValueIncVat, 1e-6);
    }
}
