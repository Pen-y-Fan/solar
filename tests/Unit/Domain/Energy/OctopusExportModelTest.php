<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy;

use App\Domain\Energy\Models\OctopusExport;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class OctopusExportModelTest extends TestCase
{
    public function testTimeIntervalCastingAndRoundTrip(): void
    {
        $model = new OctopusExport();

        $start = CarbonImmutable::parse('2025-09-28 13:00:00', 'UTC');
        $end   = CarbonImmutable::parse('2025-09-28 13:30:00', 'UTC');

        $model->setRawAttributes([
            'interval_start' => $start->toIso8601String(),
            'interval_end' => $end->toIso8601String(),
            'consumption' => 1.5,
        ], true);

        $this->assertInstanceOf(CarbonImmutable::class, $model->interval_start);
        $this->assertInstanceOf(CarbonImmutable::class, $model->interval_end);
        $this->assertSame($start->toIso8601String(), $model->interval_start->toIso8601String());
        $this->assertSame($end->toIso8601String(), $model->interval_end->toIso8601String());

        $newStart = $start->addMinutes(30);
        $newEnd = $end->addMinutes(30);
        $model->interval_end = $newEnd;
        $model->interval_start = $newStart;

        $this->assertSame($newStart->toIso8601String(), $model->interval_start->toIso8601String());
        $this->assertSame($newEnd->toIso8601String(), $model->interval_end->toIso8601String());

        $this->assertEqualsWithDelta(1.5, $model->consumption, 1e-9);
        $model->consumption = 2.0;
        $this->assertEqualsWithDelta(2.0, $model->consumption, 1e-9);
    }

    public function testUtcIso8601Parsing(): void
    {
        $model = new OctopusExport();

        $model->setRawAttributes([
            'interval_start' => '2025-09-28T13:00:00+00:00',
            'interval_end' => '2025-09-28T13:30:00+00:00',
        ], true);

        $this->assertSame('2025-09-28T13:00:00+00:00', $model->interval_start->toIso8601String());
        $this->assertSame('2025-09-28T13:30:00+00:00', $model->interval_end->toIso8601String());
    }
}
