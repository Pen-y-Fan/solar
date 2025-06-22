<?php

namespace Tests\Feature\Models;

use App\Domain\Energy\Models\OctopusImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OctopusImportTest extends TestCase
{
    use RefreshDatabase;

    public function testAnOctopusImportCanBeCreated(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'interval_start' => now()->addHours(2)->startOfHour(),
            'interval_end' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'consumption' => $estimate,
        ];
        $octopusImport = OctopusImport::create($data);

        $this->assertInstanceOf(OctopusImport::class, $octopusImport);
        $this->assertDatabaseCount(OctopusImport::class, 1);
        $this->assertSame(
            $data['interval_start']->toDateTimeString(),
            $octopusImport->interval_start->toDateTimeString()
        );
        $this->assertSame($data['interval_end']->toDateTimeString(), $octopusImport->interval_end->toDateTimeString());
        $this->assertSame($data['consumption'], $octopusImport->consumption);
    }

    public function testAnOctopusImportCanBeCreatedWithUtcIso8601DateString(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'interval_start' => now('UTC')->parse('2024-06-15T09:00:00.0000000Z')->toDateTimeString(),
            'interval_end' => now('UTC')->parse('2024-06-15T09:00:30.0000000Z')->toDateTimeString(),
            'consumption' => $estimate,
        ];
        $octopusImport = OctopusImport::create($data);

        $this->assertInstanceOf(OctopusImport::class, $octopusImport);
        $this->assertDatabaseCount(OctopusImport::class, 1);
        $this->assertSame(
            now()->parse($data['interval_start'])->toDateTimeString(),
            $octopusImport->interval_start->toDateTimeString()
        );
        $this->assertSame(
            now()
            ->parse($data['interval_end'])
            ->toDateTimeString(),
            $octopusImport->interval_end->toDateTimeString()
        );
        $this->assertSame($data['consumption'], $octopusImport->consumption);
    }

    public function testAnOctopusImportCanNotBeCreatedForTheSamePeriod(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $data = [
            'interval_start' => now()->addHours(2)->startOfHour(),
            'interval_end' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'consumption' => fake()->randomFloat(4),
        ];
        $octopusImport = OctopusImport::create($data);

        $this->assertInstanceOf(OctopusImport::class, $octopusImport);

        $newData = [
            'interval_start' => $data['interval_start'],
            'interval_end' => $data['interval_end'],
            'consumption' => fake()->randomFloat(4),
        ];

        // Will throw an exception
        OctopusImport::create($newData);
    }

    public function testAnOctopusImportCanBeUpsertedForTheSamePeriod(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'interval_start' => now()->addHours(2)->startOfHour(),
            'interval_end' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'consumption' => $estimate,
        ];

        $octopusImport = OctopusImport::create($data);

        $this->assertInstanceOf(OctopusImport::class, $octopusImport);
        $this->assertSame($data['consumption'], $octopusImport->consumption);

        $estimate1 = 2.22;
        $estimate2 = 3.22;

        $newData = [
            [
                'interval_start' => $data['interval_start']->timezone('UTC')->toDateTimeString(),
                'interval_end' => $data['interval_end']->timezone('UTC')->toDateTimeString(),
                'consumption' => $estimate1,
            ],
            [
                'interval_start' => $data['interval_start']
                    ->clone()
                    ->addMinutes(30)
                    ->timezone('UTC')
                    ->toDateTimeString(),
                'interval_end' => $data['interval_end']->clone()->addMinutes(30)->timezone('UTC')->toDateTimeString(),
                'consumption' => $estimate2,
            ],
        ];

        $additionalOctopusImport = OctopusImport::upsert(
            $newData,
            uniqueBy: ['interval_start'],
            update: ['consumption'],
        );

        $this->assertDatabaseCount(OctopusImport::class, 2);

        $this->assertSame($additionalOctopusImport, 2);

        $octopusImport->refresh();
        $this->assertInstanceOf(OctopusImport::class, $octopusImport);
        $this->assertSame($newData[0]['consumption'], $octopusImport->consumption);
        $this->assertNotSame($data['consumption'], $octopusImport->consumption);

        $newOctopusImport = OctopusImport::whereIntervalStart($newData[1]['interval_start'])->first();

        $this->assertInstanceOf(OctopusImport::class, $newOctopusImport);
        $this->assertSame($newData[1]['consumption'], $newOctopusImport->consumption);
    }
}
