<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Queries;

use App\Application\Queries\Energy\InverterConsumptionRangeQuery;
use App\Domain\Energy\Models\Inverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

final class InverterConsumptionRangeQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testReturnsConsumptionWithinRangeOrdered(): void
    {
        $start = now('UTC')->startOfDay();
        $end = $start->copy()->addDay()->subMinute();

        // Two readings within the range in chronological order
        Inverter::query()->create([
            'period' => $start->copy()->addMinutes(30),
            'consumption' => 1.25,
        ]);
        Inverter::query()->create([
            'period' => $start->copy()->addMinutes(60),
            'consumption' => 1.50,
        ]);

        /** @var InverterConsumptionRangeQuery $query */
        $query = App::make(InverterConsumptionRangeQuery::class);
        $result = $query->run($start, $end);

        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertSame('00:30:00', $first['time']);
        $this->assertSame(1.25, (float) $first['value']);

        $second = $result->last();
        $this->assertSame('01:00:00', $second['time']);
        $this->assertSame(1.50, (float) $second['value']);
    }
}
