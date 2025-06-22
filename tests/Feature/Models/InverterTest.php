<?php

namespace Tests\Feature\Models;

use App\Models\Inverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InverterTest extends TestCase
{
    use RefreshDatabase;

    public function testAInverterCanBeCreated(): void
    {
        $data = [
            'period' => now()->addHours(2)->startOfHour(),
            'yield' => fake()->randomFloat(4),
            'to_grid' => fake()->randomFloat(4),
            'from_grid' => fake()->randomFloat(4),
            'consumption' => fake()->randomFloat(4),
        ];
        $inverter = Inverter::create($data);

        $this->assertInstanceOf(Inverter::class, $inverter);
        $this->assertDatabaseCount(Inverter::class, 1);
        $this->assertSame($data['period']->toDateTimeString(), $inverter->period->toDateTimeString());
        $this->assertSame($data['yield'], $inverter->yield);
        $this->assertSame($data['to_grid'], $inverter->to_grid);
        $this->assertSame($data['from_grid'], $inverter->from_grid);
        $this->assertSame($data['consumption'], $inverter->consumption);
    }

    public function testAInverterCanBeCreatedWithUtcIso8601DateString(): void
    {

        $data = [
            'period' => now('UTC')->parse('2024-06-15T09:00:00.0000000Z'),
            'yield' => fake()->randomFloat(4),
            'to_grid' => fake()->randomFloat(4),
            'from_grid' => fake()->randomFloat(4),
            'consumption' => fake()->randomFloat(4),
        ];
        $inverter = Inverter::create($data);

        $this->assertInstanceOf(Inverter::class, $inverter);
        $this->assertDatabaseCount(Inverter::class, 1);
        $this->assertSame(now()->parse($data['period'])->toDateTimeString(), $inverter->period->toDateTimeString());
        $this->assertSame($data['yield'], $inverter->yield);
        $this->assertSame($data['to_grid'], $inverter->to_grid);
        $this->assertSame($data['from_grid'], $inverter->from_grid);
    }

    public function testAInverterCanNotBeCreatedForTheSamePeriod(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $data = [
            'period' => now()->addHours(2)->startOfHour(),
            'yield' => fake()->randomFloat(4),
        ];
        $inverter = Inverter::create($data);

        $this->assertInstanceOf(Inverter::class, $inverter);

        $newData = [
            'period' => $data['period'],
            'yield' => fake()->randomFloat(4),
        ];

        // Will throw an exception
        Inverter::create($newData);
    }

    public function testAInverterCanBeUpsertedForTheSamePeriod(): void
    {
        $data = [
            'period' => now()->addHours(2)->startOfHour(),
            'yield' => fake()->randomFloat(4),
            'to_grid' => fake()->randomFloat(4),
            'from_grid' => fake()->randomFloat(4),
            'consumption' => fake()->randomFloat(4),
        ];

        $inverter = Inverter::create($data);

        $this->assertInstanceOf(Inverter::class, $inverter);
        $this->assertSame($data['yield'], $inverter->yield);
        $this->assertSame($data['to_grid'], $inverter->to_grid);
        $this->assertSame($data['from_grid'], $inverter->from_grid);
        $this->assertSame($data['consumption'], $inverter->consumption);

        $newData = [
            [
                'period' => $data['period'],
                'yield' => fake()->randomFloat(4),
                'to_grid' => fake()->randomFloat(4),
                'from_grid' => fake()->randomFloat(4),
                'consumption' => fake()->randomFloat(4),
            ],
            [
                'period' => $data['period']->clone()->addMinutes(30),
                'yield' => fake()->randomFloat(4),
                'to_grid' => fake()->randomFloat(4),
                'from_grid' => fake()->randomFloat(4),
                'consumption' => fake()->randomFloat(4),
            ],
        ];

        $additionalInverter = Inverter::upsert(
            $newData,
            uniqueBy: ['period'],
            update: ['yield', 'to_grid', 'from_grid', 'consumption']
        );

        $this->assertDatabaseCount(Inverter::class, 2);

        $this->assertSame($additionalInverter, 2);

        $inverter->refresh();
        $this->assertInstanceOf(Inverter::class, $inverter);
        $this->assertSame($newData[0]['yield'], $inverter->yield);
        $this->assertNotSame($data['yield'], $inverter->yield);
        $this->assertNotSame($data['to_grid'], $inverter->to_grid);
        $this->assertNotSame($data['from_grid'], $inverter->from_grid);
        $this->assertNotSame($data['consumption'], $inverter->consumption);

        $newInverter = Inverter::wherePeriod($newData[1]['period'])->first();
        $this->assertInstanceOf(Inverter::class, $newInverter);
        $this->assertSame($newData[1]['yield'], $newInverter->yield);
        $this->assertSame($newData[1]['to_grid'], $newInverter->to_grid);
        $this->assertSame($newData[1]['from_grid'], $newInverter->from_grid);
        $this->assertSame($newData[1]['consumption'], $newInverter->consumption);
    }
}
