<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Queries;

use App\Application\Queries\Energy\EnergyCostBreakdownByDayQuery;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EnergyCostBreakdownByDayQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testReturnsExpectedShapeAndValues(): void
    {
        // Arrange: create a few strategies with known costs
        $s1 = Strategy::factory()->create([
            'period' => now()->startOfHour(),
            'import_value_inc_vat' => 30.00,
            'export_value_inc_vat' => 5.00,
        ]);
        $s2 = Strategy::factory()->create([
            'period' => now()->startOfHour()->addHour(),
            'import_value_inc_vat' => 10.50,
            'export_value_inc_vat' => 2.25,
        ]);

        $strategies = Strategy::query()->orderBy('period')->get();

        // Act
        $query = new EnergyCostBreakdownByDayQuery();
        $result = $query->run($strategies);

        // Assert
        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertSame((string)$s1->period, $first['valid_from']);
        $this->assertSame(30.0, $first['import_value_inc_vat']);
        $this->assertSame(5.0, $first['export_value_inc_vat']);
        $this->assertSame(25.0, $first['net_cost']);

        $last = $result->last();
        $this->assertSame((string)$s2->period, $last['valid_from']);
        $this->assertSame(10.5, $last['import_value_inc_vat']);
        $this->assertSame(2.25, $last['export_value_inc_vat']);
        $this->assertSame(8.25, $last['net_cost']);
    }

    public function testHandlesNullValuesByCoercingToZero(): void
    {
        $s = Strategy::factory()->create([
            'period' => now()->startOfHour()->addHours(3),
            'import_value_inc_vat' => null,
            'export_value_inc_vat' => null,
        ]);

        $strategies = Strategy::query()->whereKey($s->id)->get();

        $query = new EnergyCostBreakdownByDayQuery();
        $result = $query->run($strategies);

        $row = $result->first();
        $this->assertSame(0.0, $row['import_value_inc_vat']);
        $this->assertSame(0.0, $row['export_value_inc_vat']);
        // getNetCost returns null when missing either side; we coerce to float 0.0 in the query for charting
        $this->assertSame(0.0, $row['net_cost']);
    }
}
