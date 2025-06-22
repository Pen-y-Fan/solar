<?php

namespace Tests\Feature\Models;

use App\Domain\Energy\Models\OctopusExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OctopusExportTest extends TestCase
{
    use RefreshDatabase;

    public function testAnOctopusExportCanBeCreated(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'interval_start' => now()->addHours(2)->startOfHour(),
            'interval_end' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'consumption' => $estimate,
        ];
        $octopusExport = OctopusExport::create($data);

        $this->assertInstanceOf(OctopusExport::class, $octopusExport);
        $this->assertDatabaseCount(OctopusExport::class, 1);
        $this->assertSame(
            $data['interval_start']->toDateTimeString(),
            $octopusExport->interval_start->toDateTimeString()
        );
        $this->assertSame(
            $data['interval_end']->toDateTimeString(),
            $octopusExport->interval_end->toDateTimeString()
        );
        $this->assertSame($data['consumption'], $octopusExport->consumption);
    }

    public function testAnOctopusExportCanBeCreatedWithUtcIso8601DateString(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'interval_start' => now('UTC')->parse('2024-06-15T09:00:00.0000000Z')->toDateTimeString(),
            'interval_end' => now('UTC')->parse('2024-06-15T09:00:30.0000000Z')->toDateTimeString(),
            'consumption' => $estimate,
        ];
        $octopusExport = OctopusExport::create($data);

        $this->assertInstanceOf(OctopusExport::class, $octopusExport);
        $this->assertDatabaseCount(OctopusExport::class, 1);
        $this->assertSame(
            now()->parse($data['interval_start'])->toDateTimeString(),
            $octopusExport->interval_start->toDateTimeString()
        );
        $this->assertSame(
            now()->parse($data['interval_end'])->toDateTimeString(),
            $octopusExport->interval_end->toDateTimeString()
        );
        $this->assertSame($data['consumption'], $octopusExport->consumption);
    }

    public function testAnOctopusExportCanNotBeCreatedForTheSamePeriod(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $data = [
            'interval_start' => now()->addHours(2)->startOfHour(),
            'interval_end' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'consumption' => fake()->randomFloat(4),
        ];
        $octopusExport = OctopusExport::create($data);

        $this->assertInstanceOf(OctopusExport::class, $octopusExport);

        $newData = [
            'interval_start' => $data['interval_start'],
            'interval_end' => $data['interval_end'],
            'consumption' => fake()->randomFloat(4),
        ];

        // Will throw an exception
        OctopusExport::create($newData);
    }

    public function testAnOctopusExportCanBeUpsertedForTheSamePeriod(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'interval_start' => now()->addHours(2)->startOfHour(),
            'interval_end' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'consumption' => $estimate,
        ];

        $octopusExport = OctopusExport::create($data);

        $this->assertInstanceOf(OctopusExport::class, $octopusExport);
        $this->assertSame($data['consumption'], $octopusExport->consumption);

        $estimate1 = 2.22;
        $estimate2 = 3.22;

        $newData = [
            [
                'interval_start' => $data['interval_start']->timezone('UTC')->toDateTimeString(),
                'interval_end' => $data['interval_end']->timezone('UTC')->toDateTimeString(),
                'consumption' => $estimate1,
            ],
            [
                'interval_start' => $data['interval_start']->clone()->addMinutes(30)
                    ->timezone('UTC')->toDateTimeString(),
                'interval_end' => $data['interval_end']->clone()->addMinutes(30)
                    ->timezone('UTC')->toDateTimeString(),
                'consumption' => $estimate2,
            ],
        ];

        $additionalOctopusExport = OctopusExport::upsert(
            $newData,
            uniqueBy: ['interval_start'],
            update: ['consumption'],
        );

        $this->assertDatabaseCount(OctopusExport::class, 2);

        $this->assertSame($additionalOctopusExport, 2);

        $octopusExport->refresh();
        $this->assertInstanceOf(OctopusExport::class, $octopusExport);
        $this->assertSame($newData[0]['consumption'], $octopusExport->consumption);
        $this->assertNotSame($data['consumption'], $octopusExport->consumption);

        $newOctopusExport = OctopusExport::whereIntervalStart($newData[1]['interval_start'])->first();

        $this->assertInstanceOf(OctopusExport::class, $newOctopusExport);
        $this->assertSame($newData[1]['consumption'], $newOctopusExport->consumption);
    }
}
