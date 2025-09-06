<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Queries;

use App\Application\Queries\Strategy\LatestStrategiesQuery;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LatestStrategiesQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsLatestNStrategiesWithExpectedShapeAndOrder(): void
    {
        // Create 6 strategies with different created_at timestamps
        Strategy::factory()->count(6)->create();

        // Manually tweak created_at to ensure ordering
        $all = Strategy::query()->orderBy('id')->get();
        foreach ($all as $i => $s) {
            $s->created_at = now()->subMinutes(10 - $i); // later records have later timestamps
            $s->save();
        }

        $query = new LatestStrategiesQuery();
        $result = $query->run(5);

        $this->assertCount(5, $result);

        // Ensure descending by created_at (first element is most recent)
        $timestamps = $result->pluck('created_at')->all();
        $sorted = $timestamps;
        rsort($sorted, SORT_STRING);
        $this->assertSame($sorted, $timestamps);

        // Ensure keys are present and types reasonable
        $first = $result->first();
        $this->assertIsArray($first);
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('period', $first);
        $this->assertArrayHasKey('created_at', $first);
    }
}
