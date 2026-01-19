<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Strategy\Services;

use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\DTOs\StrategyCostCalculatorRequest;
use App\Domain\Strategy\Services\StrategyCostCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Carbon\CarbonImmutable;
use Tests\Fixtures\StrategyTestDto;

final class StrategyCostCalculatorTest extends TestCase
{
    private StrategyCostCalculator $calculator;
    private Collection $strategies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new StrategyCostCalculator();

        $forecast = new Forecast();
        $forecast->pv_estimate = 0.2;

        $strategy = new StrategyTestDto();
        $strategy->period = CarbonImmutable::now();
        $strategy->strategy1 = false;
        $strategy->consumption_manual = 0.5;
        $strategy->import_value_inc_vat = 0.35;
        $strategy->export_value_inc_vat = 0.15;

        $strategy->forecast = $forecast;

        $this->strategies = collect([
            $strategy
        ]);
    }

    public function testCalculateTotalCostStub(): void
    {
        $this->strategies[0]->import_value_inc_vat = 0.3;

        $result = $this->calculator->calculateTotalCost(new StrategyCostCalculatorRequest($this->strategies, 10));

        $this->assertSame(10, $result->endBattery, "Battery start is 10, with no charging");
        $this->assertCount(1, $result->batteryResults);
        $this->assertEqualsWithDelta(
            0.4,
            $result->batteryResults[0]->importAmount,
            0.01,
            "Expected import with no charging 0.2/2 = 0.1 - 0.5 = 0.4"
        );
        $this->assertEqualsWithDelta(
            0.12,
            $result->totalCost,
            0.001,
            "Expected total cost with no charging 0.4 x £0.30 = 0.12"
        );
    }

    public function testCalculateTotalCostSinglePeriodNoCharging(): void
    {
        $result = $this->calculator->calculateTotalCost(new StrategyCostCalculatorRequest($this->strategies, 100));

        // Expected: pv/2 = 0.1, cons=0.5, diff=-0.4, battery 4.0 -0.4 =3.6 =90%, import=0, export=0, cost=0
        $this->assertGreaterThanOrEqual(
            90,
            $result->endBattery,
            "Battery start is 100, with no charging"
        );
        $this->assertEqualsWithDelta(0.0, $result->totalCost, 1e-6, "The battery should be used");
        $this->assertCount(1, $result->batteryResults);
        $this->assertEqualsWithDelta(90.0, $result->batteryResults[0]->batteryPercentage, 1e-6);
    }
}
