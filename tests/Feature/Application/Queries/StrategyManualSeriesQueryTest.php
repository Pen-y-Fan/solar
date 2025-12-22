<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Queries;

use App\Application\Queries\Strategy\StrategyManualSeriesQuery;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StrategyManualSeriesQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testBuildsSeriesWithAccumulativeCosts(): void
    {
        // Arrange: create two strategies with simple values to validate accumulation
        $s1 = Strategy::factory()->create([
            'period'                    => now()->startOfHour(),
            'import_amount'             => 1.0,
            'battery_charge_amount'     => 0.5,
            'export_amount'             => 0.2,
            'import_value_inc_vat'      => 20.0, // p/kWh
            'export_value_inc_vat'      => 5.0,  // p/kWh
            'strategy_manual'           => true,
            'battery_percentage_manual' => 40,
        ]);

        $s2 = Strategy::factory()->create([
            'period'                    => now()->startOfHour()->addHour(),
            'import_amount'             => 2.0,
            'battery_charge_amount'     => 0.0,
            'export_amount'             => 0.5,
            'import_value_inc_vat'      => 10.0,
            'export_value_inc_vat'      => 2.0,
            'strategy_manual'           => false,
            'battery_percentage_manual' => 50,
        ]);

        $strategies = Strategy::query()->orderBy('period')->get();

        // Act
        $query = new StrategyManualSeriesQuery();
        $result = $query->run($strategies);

        // Assert basic shape
        $this->assertCount(2, $result);

        $first = $result->first();

        $this->assertEquals($s1->period, $first['period_end']);
        $this->assertSame(1.5, $first['import']); // 1.0 + 0.5
        $this->assertSame(0.2, $first['export']);
        // importCost = 1.5 * 20p = 30p, exportCost = 0.2 * 5p = 1p, cost = 29p
        $this->assertEqualsWithDelta(29.0, $first['cost'], 0.0001);
        $this->assertEqualsWithDelta(29.0, $first['acc_cost'], 0.0001);
        $this->assertTrue((bool)$first['charging']);
        $this->assertSame(40, $first['battery_percent']);
        $this->assertEqualsWithDelta(30.0, $first['import_accumulative_cost'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $first['export_accumulative_cost'], 0.0001);

        $last = $result->last();
        $this->assertEquals($s2->period, $last['period_end']);
        $this->assertSame(2.0, $last['import']);
        $this->assertSame(0.5, $last['export']);
        // s2: importCost = 2.0 * 10p = 20p, exportCost = 0.5 * 2p = 1p, cost = 19p
        // accumulative: 0.29 + 0.19 = 48p
        $this->assertEqualsWithDelta(19, $last['cost'], 0.0001);
        $this->assertEqualsWithDelta(48, $last['acc_cost'], 0.0001);
        $this->assertFalse((bool)$last['charging']);
        $this->assertSame(50, $last['battery_percent']);
        $this->assertEqualsWithDelta(50, $last['import_accumulative_cost'], 0.0001); // 30 + 20
        $this->assertEqualsWithDelta(2.0, $last['export_accumulative_cost'], 0.0001);  // 1 + 1
    }

    public function testHandlesMissingValues(): void
    {
        $s = Strategy::factory()->create([
            'period'                    => now()->startOfHour()->addHours(2),
            'import_amount'             => 0.0,
            'battery_charge_amount'     => 0.0,
            'export_amount'             => 0.0,
            'import_value_inc_vat'      => 0.0,
            'export_value_inc_vat'      => 0.0,
            'strategy_manual'           => null,
            'battery_percentage_manual' => null,
        ]);

        $strategies = Strategy::query()->whereKey($s->id)->get();
        $query = new StrategyManualSeriesQuery();
        $result = $query->run($strategies);

        $row = $result->first();
        $this->assertEquals($s->period, $row['period_end']);
        $this->assertSame(0.0, $row['import']);
        $this->assertSame(0.0, $row['export']);
        $this->assertSame(0.0, $row['cost']);
        $this->assertSame(0.0, $row['acc_cost']);
        $this->assertNull($row['charging']);
        $this->assertNull($row['battery_percent']);
        $this->assertSame(0.0, $row['import_accumulative_cost']);
        $this->assertSame(0.0, $row['export_accumulative_cost']);
    }
}
