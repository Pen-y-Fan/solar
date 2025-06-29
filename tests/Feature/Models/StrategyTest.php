<?php

namespace Tests\Feature\Models;

use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyTest extends TestCase
{
    use RefreshDatabase;

    public function testAStrategyCanBeCreated(): void
    {
        $data = [
            'period' => now()->startOfHour(),
            'battery_charge_amount' => fake()->randomFloat(2, 0, 500),
            'import_amount' => fake()->randomFloat(2, 0, 500),
            'export_amount' => fake()->randomFloat(2, 0, 500),
            'battery_percentage_manual' => fake()->numberBetween(0, 100),
            'strategy_manual' => fake()->boolean,
            'strategy1' => fake()->boolean,
            'strategy2' => fake()->boolean,
            'consumption_last_week' => fake()->randomFloat(2, 0, 500),
            'consumption_average' => fake()->randomFloat(2, 0, 500),
            'consumption_manual' => fake()->randomFloat(2, 0, 500),
            'import_value_inc_vat' => fake()->randomFloat(2, 0.00, 99.99),
            'export_value_inc_vat' => fake()->randomFloat(2, 0.00, 99.99),
        ];

        $strategy = Strategy::create($data);

        $this->assertInstanceOf(Strategy::class, $strategy);
        $this->assertDatabaseCount(Strategy::class, 1);

        foreach ($data as $key => $value) {
            if ($value instanceof \Illuminate\Support\Carbon) {
                $this->assertSame($value->toDateTimeString(), $strategy->{$key}->toDateTimeString());
            } else {
                $this->assertSame($value, $strategy->{$key});
            }
        }

        $strategy->refresh();
        $this->assertInstanceOf(Strategy::class, $strategy);
        $this->assertEqualsWithDelta(
            $data['consumption_last_week'] * $data['import_value_inc_vat'],
            $strategy->consumption_last_week_cost,
            0.001
        );
        $this->assertEqualsWithDelta(
            $data['consumption_average'] * $data['import_value_inc_vat'],
            $strategy->consumption_average_cost,
            0.001
        );
    }

    public function testAStrategyCanNotBeCreatedWithNonUniqueTimestamp(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $timestamp = now()->startOfHour();

        $data1 = [
            'period' => $timestamp,
            'strategy_manual' => fake()->boolean,
        ];

        $data2 = [
            'period' => $timestamp,
            'strategy_manual' => fake()->boolean,
        ];

        Strategy::create($data1);
        Strategy::create($data2); // This should throw an exception due to unique constraint
    }

    public function testAStrategyCanBeUpdated(): void
    {
        $strategy = Strategy::factory()->create([
            'period' => now()->startOfHour(),
            'battery_percentage_manual' => 50,
            'strategy_manual' => false,
        ]);

        $this->assertSame(50, $strategy->battery_percentage_manual);
        $this->assertFalse($strategy->strategy_manual);

        $strategy->update([
            'battery_percentage_manual' => 75,
            'strategy_manual' => true,
        ]);

        $this->assertSame(75, $strategy->battery_percentage_manual);
        $this->assertTrue($strategy->strategy_manual);
    }

    public function testAStrategyCanHaveOneRelatedAgileImport()
    {
        $strategy = Strategy::factory()->create();

        $agileImport = AgileImport::create([
            'valid_from' => $strategy->period,
            'valid_to' => $strategy->period->clone()->addMinutes(30),
            'value_exc_vat' => fake()->randomFloat(4),
            'value_inc_vat' => fake()->randomFloat(4),
        ]);

        $strategy->load('importCost');

        $this->assertInstanceOf(AgileImport::class, $strategy->importCost);
        $this->assertSame($strategy->importCost->id, $agileImport->id);
        $this->assertSame($strategy->importCost->value_exc_vat, $agileImport->value_exc_vat);
        $this->assertSame($strategy->importCost->value_inc_vat, $agileImport->value_inc_vat);
    }

    public function testAStrategyCanHaveOneRelatedAgileExport()
    {
        $strategy = Strategy::factory()->create();

        $agileExport = AgileExport::create([
            'valid_from' => $strategy->period,
            'valid_to' => $strategy->period->clone()->addMinutes(30),
            'value_exc_vat' => fake()->randomFloat(4),
            'value_inc_vat' => fake()->randomFloat(4),

        ]);


        $strategy->load('exportCost');

        $this->assertInstanceOf(AgileExport::class, $strategy->exportCost);
        $this->assertSame($strategy->exportCost->id, $agileExport->id);
        $this->assertSame($strategy->exportCost->value_exc_vat, $agileExport->value_exc_vat);
        $this->assertSame($strategy->exportCost->value_inc_vat, $agileExport->value_inc_vat);
    }

    public function testAStrategyCanHaveOneRelatedForecast()
    {
        $strategy = Strategy::factory()->create();
        $estimate = fake()->randomFloat(4);
        $forecast = Forecast::create([
            'period_end' => $strategy->period,
            'pv_estimate' => $estimate,
            'pv_estimate10' => $estimate * 0.1,
            'pv_estimate90' => $estimate * 1.1,

        ]);

        $strategy->load('forecast');

        $this->assertInstanceOf(Forecast::class, $strategy->forecast);
        $this->assertSame($strategy->forecast->id, $forecast->id);
        $this->assertEqualsWithDelta($strategy->forecast->pv_estimate, $forecast->pv_estimate, 0.001);
        $this->assertEqualsWithDelta($strategy->forecast->pv_estimate10, $forecast->pv_estimate10, 0.001);
        $this->assertEqualsWithDelta($strategy->forecast->pv_estimate90, $forecast->pv_estimate90, 0.001);
    }

    public function testAStrategyCanHaveOneRelatedActualForecast()
    {
        $strategy = Strategy::factory()->create();

        $estimate = fake()->randomFloat(4);
        $actualForecast = ActualForecast::create([
            'period_end' => $strategy->period,
            'pv_estimate' => $estimate,
            'pv_estimate10' => $estimate * 0.1,
            'pv_estimate90' => $estimate * 1.1,

        ]);

        $strategy->load('actualForecast');

        $this->assertInstanceOf(ActualForecast::class, $strategy->actualForecast);
        $this->assertSame($strategy->actualForecast->id, $actualForecast->id);
        $this->assertEqualsWithDelta($strategy->actualForecast->pv_estimate, $actualForecast->pv_estimate, 0.001);
        $this->assertEqualsWithDelta($strategy->actualForecast->pv_estimate10, $actualForecast->pv_estimate10, 0.001);
        $this->assertEqualsWithDelta($strategy->actualForecast->pv_estimate90, $actualForecast->pv_estimate90, 0.001);
    }
}
