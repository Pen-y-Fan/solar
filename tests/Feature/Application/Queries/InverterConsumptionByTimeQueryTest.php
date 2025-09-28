<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Queries;

use App\Application\Queries\Energy\InverterConsumptionByTimeQuery;
use App\Domain\Energy\Models\Inverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

final class InverterConsumptionByTimeQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testReturnsConsumptionGroupedByTimeOfDay(): void
    {
        $d1 = now('UTC')->startOfDay();

        Inverter::query()->create([
            'period' => $d1->copy()->addHours(10), // 10:00 UTC
            'consumption' => 2.5,
        ]);

        /** @var InverterConsumptionByTimeQuery $query */
        $query = App::make(InverterConsumptionByTimeQuery::class);
        $result = $query->run();

        // Should contain at least one entry with the expected value
        $this->assertGreaterThanOrEqual(1, $result->count());
        $first = $result->first();
        $this->assertArrayHasKey('time', $first);
        $this->assertArrayHasKey('value', $first);
        $this->assertEquals("10:00:00", $first['time']);
        $this->assertEquals(2.5, (float) $first['value']);
    }
}
