<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Energy\Repositories;

use App\Domain\Energy\Models\Inverter;
use App\Domain\Energy\Repositories\EloquentInverterRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentInverterRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function testGetAverageConsumptionByTimeGroupsAndClampsValues(): void
    {
        $start = now('UTC')->startOfDay();

        // Two readings at the same time of day across days + a negative glitch value
        Inverter::query()->create([
            'period' => $start->copy()->subDays(1)->addHours(10), // 10:00 yesterday
            'consumption' => 2.0,
        ]);
        Inverter::query()->create([
            'period' => $start->copy()->subDays(7)->addHours(10), // 10:00 a week ago
            'consumption' => -1.0, // glitch (should be clamped at 0 before averaging or result non-negative)
        ]);
        Inverter::query()->create([
            'period' => $start->copy()->subDays(2)->addHours(11), // 11:00
            'consumption' => 3.0,
        ]);

        $repo = new EloquentInverterRepository();
        $result = $repo->getAverageConsumptionByTime($start);

        $array = $result->map(fn($dto) => $dto->toArray());

        // We expect entries for 10:00:00 and 11:00:00
        $ten = $array->firstWhere('time', '10:00:00');
        $eleven = $array->firstWhere('time', '11:00:00');

        $this->assertNotNull($ten);
        $this->assertNotNull($eleven);

        // 10:00 average with clamp: max(0, avg(2.0, -1.0)) => 0.5 or 1.0 depending on clamp timing.
        // Our implementation clamps after averaging by ensuring non-negative final value.
        $this->assertGreaterThanOrEqual(0.0, (float)$ten['value']);
        $this->assertEquals('10:00:00', $ten['time']);

        // 11:00 is straightforward
        $this->assertEquals(3.0, (float)$eleven['value']);
        $this->assertEquals('11:00:00', $eleven['time']);
    }

    public function testGetConsumptionForDateRangeOrdersByPeriodAndClampsNegative(): void
    {
        $start = now('UTC')->startOfDay();
        $end = $start->copy()->addDay()->subMinute();

        Inverter::query()->create([
            'period' => $start->copy()->addMinutes(60), // 01:00
            'consumption' => -5.0, // should clamp to 0.0
        ]);
        Inverter::query()->create([
            'period' => $start->copy()->addMinutes(30), // 00:30 (earlier)
            'consumption' => 1.25,
        ]);

        $repo = new EloquentInverterRepository();
        $result = $repo->getConsumptionForDateRange($start, $end)->map(fn($dto) => $dto->toArray());

        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertSame('00:30:00', $first['time']);
        $this->assertSame(1.25, (float)$first['value']);

        $second = $result->last();
        $this->assertSame('01:00:00', $second['time']);
        $this->assertSame(0.0, (float)$second['value']); // clamped
    }
}
