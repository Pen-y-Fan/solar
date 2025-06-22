<?php

namespace Tests\Feature\Models;

use App\Domain\Forecasting\Models\ActualForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActualForecastTest extends TestCase
{
    use RefreshDatabase;

    public function testAnActualForecastCanBeCreated(): void
    {
        $data = [
            'period_end' => now()->addHours(2)->startOfHour(),
            'pv_estimate' => fake()->randomFloat(4),
        ];
        $actualForecast = ActualForecast::create($data);

        $this->assertInstanceOf(ActualForecast::class, $actualForecast);
        $this->assertDatabaseCount(ActualForecast::class, 1);
        $this->assertSame($data['period_end']->toDateTimeString(), $actualForecast->period_end->toDateTimeString());
        $this->assertSame($data['pv_estimate'], $actualForecast->pv_estimate);
    }

    public function testAnActualForecastCanBeCreatedWithUtcIso8601DateString(): void
    {
        $data = [
            'period_end' => now('UTC')->parse('2024-06-15T09:00:00.0000000Z'),
            'pv_estimate' => 1.3873,
        ];
        $actualForecast = ActualForecast::create($data);

        $this->assertInstanceOf(ActualForecast::class, $actualForecast);
        $this->assertDatabaseCount(ActualForecast::class, 1);
        $this->assertSame(
            now()->parse($data['period_end'])->toDateTimeString(),
            $actualForecast->period_end->toDateTimeString()
        );
        $this->assertSame($data['pv_estimate'], $actualForecast->pv_estimate);
    }

    public function testAnActualForecastCanNotBeCreatedForTheSamePeriod(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $data = [
            'period_end' => now()->addHours(2)->startOfHour(),
            'pv_estimate' => fake()->randomFloat(4),
        ];
        $actualForecast = ActualForecast::create($data);

        $this->assertInstanceOf(ActualForecast::class, $actualForecast);

        $newData = [
            'period_end' => $data['period_end'],
            'pv_estimate' => fake()->randomFloat(4),
        ];

        ActualForecast::create($newData);
    }

    public function testAnActualForecastCanBeUpsertedForTheSamePeriod(): void
    {
        $data = [
            'period_end' => now()->addHours(2)->startOfHour(),
            'pv_estimate' => 1.11,
        ];
        $actualForecast = ActualForecast::create($data);

        $this->assertInstanceOf(ActualForecast::class, $actualForecast);
        $this->assertSame($data['pv_estimate'], $actualForecast->pv_estimate);

        $newData = [
            [
                'period_end' => $data['period_end'],
                'pv_estimate' => 2.22,
            ],
            [
                'period_end' => $data['period_end']->clone()->addMinutes(30),
                'pv_estimate' => 3.22,
            ],
        ];

        $additionalActualForecast = ActualForecast::upsert(
            $newData,
            uniqueBy: ['period_end'],
            update: ['pv_estimate']
        );

        $this->assertDatabaseCount(ActualForecast::class, 2);

        $this->assertSame($additionalActualForecast, 2);

        $actualForecast->refresh();
        $this->assertInstanceOf(ActualForecast::class, $actualForecast);
        $this->assertSame($newData[0]['pv_estimate'], $actualForecast->pv_estimate);
        $this->assertNotSame($data['pv_estimate'], $actualForecast->pv_estimate);

        $newForecast = ActualForecast::wherePeriodEnd($newData[1]['period_end'])->first();
        $this->assertInstanceOf(ActualForecast::class, $newForecast);
        $this->assertSame($newData[1]['pv_estimate'], $newForecast->pv_estimate);
    }
}
