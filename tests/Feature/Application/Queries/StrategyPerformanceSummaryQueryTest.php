<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Queries;

use App\Application\Queries\Strategy\StrategyPerformanceSummaryQuery;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class StrategyPerformanceSummaryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testReturnsEmptyCollectionWhenNoRows(): void
    {
        $query = new StrategyPerformanceSummaryQuery();
        $start = Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC');
        $end = Carbon::create(2025, 1, 1, 23, 59, 59, 'UTC');

        $result = $query->run($start, $end);

        $this->assertCount(0, $result);
    }

    public function testAggregatesDailyKpis(): void
    {
        // Day 1 rows
        Strategy::factory()->create([
            'period' => Carbon::create(2025, 1, 1, 12, 0, 0, 'UTC'),
            'import_amount' => 2.0,
            'import_value_inc_vat' => 10.0,
            'battery_charge_amount' => 1.0,
            'export_amount' => 0.5,
            'export_value_inc_vat' => 8.0,
        ]);
        Strategy::factory()->create([
            'period' => Carbon::create(2025, 1, 1, 13, 0, 0, 'UTC'),
            'import_amount' => 1.0,
            'import_value_inc_vat' => 20.0,
            'battery_charge_amount' => 0.0,
            'export_amount' => 0.2,
            'export_value_inc_vat' => 7.0,
        ]);

        // Day 2 row
        Strategy::factory()->create([
            'period' => Carbon::create(2025, 1, 2, 12, 0, 0, 'UTC'),
            'import_amount' => 3.0,
            'import_value_inc_vat' => 15.0,
            'battery_charge_amount' => 0.5,
            'export_amount' => 1.0,
            'export_value_inc_vat' => 5.0,
        ]);

        $query = new StrategyPerformanceSummaryQuery();
        $start = Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC');
        $end = Carbon::create(2025, 1, 2, 23, 59, 59, 'UTC');

        $result = $query->run($start, $end);

        $this->assertCount(2, $result);
        $day1 = $result->firstWhere('date', '2025-01-01');
        $day2 = $result->firstWhere('date', '2025-01-02');

        // Day1 expected:
        // total_import = 3.0
        // import_cost = 2*10 + 1*20 = 40
        // total_battery = 1.0
        // battery_cost = 1*10 + 0*20 = 10
        // export_kwh = 0.7
        // export_revenue = 0.5*8 + 0.2*7 = 5.4
        // self_consumption = max(0, 3.0 - 0.7) = 2.3
        // net_cost = 40 + 10 - 5.1 = 44.9
        $this->assertEqualsWithDelta(3.0, $day1['total_import_kwh'], 0.001);
        $this->assertEqualsWithDelta(40.0, $day1['import_cost_pence'], 0.001);
        $this->assertEqualsWithDelta(1.0, $day1['total_battery_kwh'], 0.001);
        $this->assertEqualsWithDelta(10.0, $day1['battery_cost_pence'], 0.001);
        $this->assertEqualsWithDelta(0.7, $day1['export_kwh'], 0.001);
        $this->assertEqualsWithDelta(5.4, $day1['export_revenue_pence'], 0.001);
        $this->assertEqualsWithDelta(2.3, $day1['self_consumption_kwh'], 0.001);
        $this->assertEqualsWithDelta(44.6, $day1['net_cost_pence'], 0.001);

        // Day2 expected:
        // import = 3.0, cost = 3*15 = 45
        // battery = 0.5, battery_cost = 0.5*15 = 7.5
        // export = 1.0, revenue = 1*5 = 5
        // self_consumption = 2.0
        // net = 45 + 7.5 - 5 = 47.5
        $this->assertEqualsWithDelta(3.0, $day2['total_import_kwh'], 0.001);
        $this->assertEqualsWithDelta(45.0, $day2['import_cost_pence'], 0.001);
        $this->assertEqualsWithDelta(0.5, $day2['total_battery_kwh'], 0.001);
        $this->assertEqualsWithDelta(7.5, $day2['battery_cost_pence'], 0.001);
        $this->assertEqualsWithDelta(1.0, $day2['export_kwh'], 0.001);
        $this->assertEqualsWithDelta(5.0, $day2['export_revenue_pence'], 0.001);
        $this->assertEqualsWithDelta(2.0, $day2['self_consumption_kwh'], 0.001);
        $this->assertEqualsWithDelta(47.5, $day2['net_cost_pence'], 0.001);
    }
}
