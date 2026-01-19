<?php

/** @noinspection PhpExpressionResultUnusedInspection */

declare(strict_types=1);

namespace Tests\Unit\Domain\Strategy;

use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use App\Domain\Strategy\Actions\GenerateStrategyAction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Mockery;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Fixtures\StrategyTestDto;

final class GenerateStrategyActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function buildStrategyObject(int $id, CarbonInterface $start, object $forecastData): StrategyTestDto
    {
        $strategy = new StrategyTestDto();
        $strategy->id = $id;
        $strategy->period = $start;
        $strategy->forecast = $forecastData;
        return $strategy;
    }

    private function makeAction(): GenerateStrategyAction
    {
        $stubRepo = new class implements InverterRepositoryInterface {
            public function getAverageConsumptionByTime(
                CarbonInterface $startDate
            ): Collection {
                return collect();
            }

            public function getConsumptionForDateRange(
                CarbonInterface $startDate,
                CarbonInterface $endDate
            ): Collection {
                return collect();
            }
        };

        return new GenerateStrategyAction($stubRepo);
    }

    private function forecastEntry(CarbonInterface $period, ?float $import, ?float $export, float $pv): object
    {
        // Create simple value objects with value_inc_vat properties like Eloquent models would expose
        $importCost = $import === null ? null : (object)['value_inc_vat' => $import];
        $exportCost = $export === null ? null : (object)['value_inc_vat' => $export];

        return (object)[
            'period_end'  => $period,
            'importCost'  => $importCost,
            'exportCost'  => $exportCost,
            'pv_estimate' => $pv,
        ];
    }

    /**
     * @throws ReflectionException
     */
    public function testCalculateBaselineCostsSetsBaselineCostAndValidatesEndBatteryWhenPvHigh(): void
    {
        // Arrange
        $action = $this->makeAction();

        // Set in BST to confirm UTC conversion works.
        $start = CarbonImmutable::create(2025, 7, 2, 14, 00, 0, 'Europe/London')->timezone('UTC');

        $strategies = collect();
        $periods = new CarbonPeriod($start, '30 minutes', 4);
        foreach ($periods as $key => $period) {
            $forecast = $this->forecastEntry($period, 0.50, 0.11, 1.0);
            $strategies->add($this->buildStrategyObject($key, $period, $forecast));
        }
        $action->baseStrategy = $strategies;

        $refMethod = new ReflectionMethod($action, 'calculateBaselineCosts');
        $refMethod->setAccessible(true);
        $refMethod->invoke($action);

        $this->assertNotNull($action->baselineCost);
        $this->assertEqualsWithDelta(0.0, $action->baselineCost, 1e-6);
        $this->assertCount(4, $action->baselineBatteryResults);

        $action->baseStrategy->each(
            fn($strategy) => $this->assertFalse($strategy->strategy_manual, 'Charge should be false')
        );
        $this->assertGreaterThanOrEqual(90.0, $action->baselineEndBattery);
        $this->assertTrue($action->baselineValid);
        $this->assertEmpty($action->errors);
    }

    /**
     * @throws ReflectionException
     */
    public function testCalculateBaselineCostsChargesTrailingPeriodsToMaintainEndBattery(): void
    {
        // Arrange
        $action = $this->makeAction();

        // Set in BST to confirm UTC conversion works.
        $start = CarbonImmutable::create(2025, 7, 2, 14, 00, 0, 'Europe/London')->timezone('UTC');

        $strategies = collect();
        $periods = new CarbonPeriod($start, '30 minutes', 4);
        foreach ($periods as $key => $period) {
            $forecast = $this->forecastEntry($period, 0.30, 0.15, 0.0);
            $strategies->add($this->buildStrategyObject($key, $period, $forecast));
        }
        $action->baseStrategy = $strategies;

        $refMethod = new ReflectionMethod($action, 'calculateBaselineCosts');
        $refMethod->setAccessible(true);
        $refMethod->invoke($action);

        $this->assertNotNull($action->baselineCost);
        $this->assertGreaterThan(0.0, $action->baselineCost);
        $this->assertEquals(
            [false, false, true, true],
            $action->baseStrategy->map(fn($strategy) => $strategy->strategy2)->toArray()
        );
        $this->assertGreaterThanOrEqual(90.0, $action->baselineEndBattery);
        $this->assertTrue($action->baselineValid);
        $this->assertEmpty($action->errors);
    }
    public function testCalculateBaselineCostsFailsWhenEndBatteryLow(): void
    {
        // Arrange
        $action = $this->makeAction();

        // Set in BST to confirm UTC conversion works.
        $start = CarbonImmutable::create(2025, 7, 2, 14, 00, 0, 'Europe/London')->timezone('UTC');

        $strategies = collect();
        // It is not possible to fully charge in three periods
        $periods = new CarbonPeriod($start, '30 minutes', 2);
        foreach ($periods as $key => $period) {
            $forecast = $this->forecastEntry($period, 0.30, 0.15, 0.0);
            $strategies->add($this->buildStrategyObject($key, $period, $forecast));
        }
        $action->baseStrategy = $strategies;
        $refBattery = new ReflectionProperty(GenerateStrategyAction::class, 'startBatteryPercentage');
        $refBattery->setAccessible(true);
        $refBattery->setValue($action, 10);

        $refMethod = new ReflectionMethod($action, 'calculateBaselineCosts');
        $refMethod->setAccessible(true);
        $refMethod->invoke($action);

        $this->assertNull($action->baselineCost);

        // Baseline will try and charge the battery
        $this->assertEquals(
            [true, true],
            $action->baseStrategy->map(fn($strategy) => $strategy->strategy2)->toArray()
        );
        $this->assertLessThanOrEqual(90.0, $action->baselineCost);
        $this->assertFalse($action->baselineValid);
        $this->assertNotEmpty($action->errors);
        $this->assertStringStartsWith("Baseline end battery", $action->errors[0]);
    }

    /**
     * @throws ReflectionException
     */
    public function testCalculateStrategyCostsSetsOptimizedCostAndValidatesEndBattery(): void
    {
        // Arrange
        $action = $this->makeAction();

        // Set in BST to confirm UTC conversion works.
        $start = CarbonImmutable::create(2025, 7, 2, 14, 00, 0, 'Europe/London')->timezone('UTC');

        $strategies = collect();
        $periods = new CarbonPeriod($start, '30 minutes', 4);
        foreach ($periods as $key => $period) {
            $forecast = $this->forecastEntry($period, 0.30, 0.15, 0.2);
            $strategies->add($this->buildStrategyObject($key, $period, $forecast));
        }
        $action->baseStrategy = $strategies;

        $refMethod = new ReflectionMethod($action, 'calculateStrategyCosts');
        $refMethod->setAccessible(true);

        // Act
        $refMethod->invoke($action);

        // Assert
        $this->assertNotNull($action->optimizedCost);
        $this->assertGreaterThan(0.0, $action->optimizedCost);
        $this->assertGreaterThanOrEqual(90.0, $action->optimizedEndBattery);
        $this->assertTrue($action->optimizedValid);
        $this->assertEmpty($action->errors);
    }

    /**
     * @throws ReflectionException
     */
    public function testCalculateStrategyCostsFailsWhenEndBatteryLow(): void
    {
        $action = $this->makeAction();
        // Set in BST to confirm UTC conversion works.
        $start = CarbonImmutable::create(2025, 7, 2, 14, 00, 0, 'Europe/London')->timezone('UTC');

        $strategies = collect();
        // It is not possible to fully charge in two periods
        $periods = new CarbonPeriod($start, '30 minutes', 2);

        foreach ($periods as $key => $period) {
            $forecast = $this->forecastEntry($period, 0.30, 0.15, 0.1);
            $strategies->add($this->buildStrategyObject($key, $period, $forecast));
        }

        $refBattery = new ReflectionProperty(GenerateStrategyAction::class, 'startBatteryPercentage');
        $refBattery->setAccessible(true);
        $refBattery->setValue($action, 10);

        $action->baseStrategy = $strategies;

        $refMethod = new ReflectionMethod($action, 'calculateStrategyCosts');
        $refMethod->setAccessible(true);
        $refMethod->invoke($action);

        $this->assertNotNull($action->optimizedCost);
        $this->assertGreaterThan(0.0, $action->optimizedCost);
        $this->assertLessThanOrEqual(90.0, $action->optimizedEndBattery);
        $this->assertFalse($action->optimizedValid);
        $this->assertNotEmpty($action->errors);
        $this->assertStringStartsWith("Optimise end battery", $action->errors[0]);
    }

    /**
     * @throws ReflectionException
     */
    public function testCalculateStrategyCostsPassesWhenBaselineChargingUsed(): void
    {
        $action = $this->makeAction();
        // Set in BST to confirm UTC conversion works.
        $start = CarbonImmutable::create(2025, 7, 2, 14, 00, 0, 'Europe/London')->timezone('UTC');

        $strategies = collect();
        $periods = new CarbonPeriod($start, '30 minutes', 4);

        foreach ($periods as $key => $period) {
            $forecast = $this->forecastEntry($period, 0.30, 0.15, 0.1);
            $chargedBaseStrategy = $this->buildStrategyObject($key, $period, $forecast);
            $chargedBaseStrategy->strategy2 = true;
            $strategies->add($chargedBaseStrategy);
        }

        $action->baseStrategy = $strategies;

        $refMethod = new ReflectionMethod($action, 'calculateStrategyCosts');
        $refMethod->setAccessible(true);
        $refMethod->invoke($action);

        $this->assertNotNull($action->optimizedCost);
        $this->assertGreaterThan(0.0, $action->optimizedCost);
        $this->assertGreaterThanOrEqual(90.0, $action->optimizedEndBattery);
        $this->assertTrue($action->optimizedValid);
        $this->assertEmpty($action->errors);
    }

    /**
     * @throws ReflectionException
     */
    public function testCalculateFinalCostPopulatesManualWithBestWhenNullOptimizedBattery(): void
    {
        $action = $this->makeAction();
        $start = CarbonImmutable::now();
        $strategies = collect();
        $periods = new CarbonPeriod($start, '30 minutes', 4);
        foreach ($periods as $i => $period) {
            $forecast = $this->forecastEntry($period, 0.35, 0.15, 0.0);
            $strategy = $this->buildStrategyObject($i, $period, $forecast);
            $strategy->consumption_manual = 0.0;
            $strategy->import_value_inc_vat = 0.35;
            $strategy->strategy1 = $i === 0; // charge first
            $strategy->strategy2 = false;
            $strategy->strategy_manual = false;
            $strategies->add($strategy);
        }
        $action->baseStrategy = $strategies;
        $refProp = new ReflectionProperty(GenerateStrategyAction::class, 'startBatteryPercentage');
        $refProp->setAccessible(true);
        $refProp->setValue($action, 100);
        $refCostProp = new ReflectionProperty(GenerateStrategyAction::class, 'optimizedCost');
        $refCostProp->setAccessible(true);
        $refCostProp->setValue($action, 5.0);
        $refBaseProp = new ReflectionProperty(GenerateStrategyAction::class, 'baselineCost');
        $refBaseProp->setAccessible(true);
        $refBaseProp->setValue($action, 10.0);

        $refMethod = new ReflectionMethod($action, 'calculateFinalCost');
        $refMethod->setAccessible(true);
        $refMethod->invoke($action);

        $action->baseStrategy->each(function ($strategy, $index) {
            $expectedManual = $index === 0;
            $this->assertSame($expectedManual, $strategy->strategy_manual);
            $this->assertEqualsWithDelta(100.0, $strategy->battery_percentage1, 1e-6);
            $this->assertEqualsWithDelta(0.0, $strategy->battery_charge_amount, 1e-6);
            $this->assertEqualsWithDelta(100.0, $strategy->battery_percentage_manual, 1e-6);
            $this->assertEqualsWithDelta(0.0, $strategy->import_amount, 1e-6);
            $this->assertEqualsWithDelta(0.0, $strategy->export_amount, 1e-6);
        });
    }

    /**
     * @throws ReflectionException
     */
    public function testCalculateFinalCostPreservesExistingManualFlags(): void
    {
        $action = $this->makeAction();
        $start = CarbonImmutable::now();
        $strategies = collect();
        $periods = new CarbonPeriod($start, '30 minutes', 4);
        foreach ($periods as $i => $period) {
            $forecast = $this->forecastEntry($period, 0.35, 0.15, 0.0);
            $strategy = $this->buildStrategyObject($i, $period, $forecast);
            $strategy->consumption_manual = 0.0;
            $strategy->import_value_inc_vat = 0.35;
            $strategy->strategy1 = false;
            $strategy->strategy2 = false;
            $strategy->strategy_manual = $i === 1; // preserve middle
            $strategies->add($strategy);
        }
        $action->baseStrategy = $strategies;
        $refProp = new ReflectionProperty(GenerateStrategyAction::class, 'startBatteryPercentage');
        $refProp->setAccessible(true);
        $refProp->setValue($action, 100);
        $refCostProp = new ReflectionProperty(GenerateStrategyAction::class, 'optimizedCost');
        $refCostProp->setAccessible(true);
        $refCostProp->setValue($action, 5.0); // opt battery, but preserve
        $refBaseProp = new ReflectionProperty(GenerateStrategyAction::class, 'baselineCost');
        $refBaseProp->setAccessible(true);
        $refBaseProp->setValue($action, 10.0);

        $refMethod = new ReflectionMethod($action, 'calculateFinalCost');
        $refMethod->setAccessible(true);
        $refMethod->invoke($action);

        $this->assertTrue($action->baseStrategy[1]->strategy_manual); // preserved
        $this->assertFalse($action->baseStrategy[0]->strategy_manual); // set to strategy1=false
    }
}
