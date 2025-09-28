<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy;

use App\Domain\Energy\Models\OctopusImport;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class OctopusImportModelTest extends TestCase
{
    public function testTimeIntervalCastingAndRoundTrip(): void
    {
        $model = new OctopusImport();

        $start = CarbonImmutable::parse('2025-09-28 12:00:00', 'UTC');
        $end   = CarbonImmutable::parse('2025-09-28 12:30:00', 'UTC');

        // Seed raw attributes as if from DB/API
        $model->setRawAttributes([
            'interval_start' => $start->toIso8601String(),
            'interval_end' => $end->toIso8601String(),
            'consumption' => 0.1234,
        ], true);

        $this->assertInstanceOf(CarbonImmutable::class, $model->interval_start);
        $this->assertInstanceOf(CarbonImmutable::class, $model->interval_end);
        $this->assertSame($start->toIso8601String(), $model->interval_start->toIso8601String());
        $this->assertSame($end->toIso8601String(), $model->interval_end->toIso8601String());

        // Update via accessors
        $newStart = $start->addMinutes(30);
        $newEnd = $end->addMinutes(30);
        $model->interval_end = $newEnd; // set end first to avoid transient equal
        $model->interval_start = $newStart;

        $this->assertSame($newStart->toIso8601String(), $model->interval_start->toIso8601String());
        $this->assertSame($newEnd->toIso8601String(), $model->interval_end->toIso8601String());

        // Consumption is a raw float; ensure value preserved
        $this->assertEqualsWithDelta(0.1234, $model->consumption, 1e-9);
        $model->consumption = 0.5;
        $this->assertEqualsWithDelta(0.5, $model->consumption, 1e-9);
    }

    public function testUtcIso8601Parsing(): void
    {
        $model = new OctopusImport();

        $model->setRawAttributes([
            'interval_start' => '2025-09-28T10:00:00+00:00',
            'interval_end' => '2025-09-28T10:30:00+00:00',
        ], true);

        $this->assertSame('2025-09-28T10:00:00+00:00', $model->interval_start->toIso8601String());
        $this->assertSame('2025-09-28T10:30:00+00:00', $model->interval_end->toIso8601String());
    }
}
