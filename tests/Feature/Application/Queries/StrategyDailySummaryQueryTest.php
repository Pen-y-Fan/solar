<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Queries;

use App\Application\Queries\Strategy\StrategyDailySummaryQuery;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class StrategyDailySummaryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsDailySummaryWithExpectedShapeAndValues(): void
    {
        $day = Carbon::parse('2024-06-15 00:00:00', 'Europe/London');

        // Compute the exact UTC window used by the query for this London day
        $start = Carbon::parse($day->format('Y-m-d'), 'Europe/London')->startOfDay()->timezone('UTC');
        $end = Carbon::parse($day->format('Y-m-d'), 'Europe/London')->endOfDay()->timezone('UTC');

        // Create strategies firmly within the target window
        Strategy::factory()->create([
            'period' => $start->clone()->addHours(1),
            'import_amount' => 2.5,
            'export_amount' => 1.0,
            'import_value_inc_vat' => 25.0,
            'export_value_inc_vat' => 10.0,
        ]);

        Strategy::factory()->create([
            'period' => $start->clone()->addHours(2),
            'import_amount' => 1.5,
            'export_amount' => 0.5,
            'import_value_inc_vat' => 35.0,
            'export_value_inc_vat' => 15.0,
        ]);

        // A row just outside the day should not be included
        Strategy::factory()->create([
            'period' => $start->clone()->subMinute(),
            'import_amount' => 100,
            'export_amount' => 100,
            'import_value_inc_vat' => 999,
            'export_value_inc_vat' => 999,
        ]);

        $query = new StrategyDailySummaryQuery();
        $summary = $query->run($day);

        $this->assertArrayHasKey('date', $summary);
        $this->assertArrayHasKey('count', $summary);
        $this->assertArrayHasKey('total_import_kwh', $summary);
        $this->assertArrayHasKey('total_export_kwh', $summary);
        $this->assertArrayHasKey('avg_import_value_inc_vat', $summary);
        $this->assertArrayHasKey('avg_export_value_inc_vat', $summary);
        $this->assertArrayHasKey('net_cost_estimate', $summary);

        $this->assertSame($day->format('Y-m-d'), $summary['date']);
        $this->assertSame(2, $summary['count']);
        $this->assertEquals(4.0, $summary['total_import_kwh']); // 2.5 + 1.5
        $this->assertEquals(1.5, $summary['total_export_kwh']); // 1.0 + 0.5

        // Averages of 25 and 35 = 30; of 10 and 15 = 12.5
        $this->assertEquals(30.0, $summary['avg_import_value_inc_vat']);
        $this->assertEquals(12.5, $summary['avg_export_value_inc_vat']);

        // Net cost estimate = 4.0 * 30.0 - 1.5 * 12.5 = 120 - 18.75 = 101.25
        $this->assertEquals(101.25, $summary['net_cost_estimate']);
    }

    public function testItHandlesNoRowsGracefully(): void
    {
        $day = Carbon::parse('2024-06-15 00:00:00', 'Europe/London');
        $query = new StrategyDailySummaryQuery();
        $summary = $query->run($day);

        $this->assertSame($day->format('Y-m-d'), $summary['date']);
        $this->assertSame(0, $summary['count']);
        $this->assertEquals(0.0, $summary['total_import_kwh']);
        $this->assertEquals(0.0, $summary['total_export_kwh']);
        $this->assertEquals(0.0, $summary['avg_import_value_inc_vat']);
        $this->assertEquals(0.0, $summary['avg_export_value_inc_vat']);
        $this->assertEquals(0.0, $summary['net_cost_estimate']);
    }
}
