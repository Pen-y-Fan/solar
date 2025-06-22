<?php

namespace Models;

use App\Models\Forecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_forecast_can_be_created(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'period_end' => now()->addHours(2)->startOfHour(),
            'pv_estimate' => $estimate,
            'pv_estimate10' => $estimate * 0.1,
            'pv_estimate90' => $estimate * 1.1,
        ];
        $forecast = Forecast::create($data);

        $this->assertInstanceOf(Forecast::class, $forecast);
        $this->assertDatabaseCount(Forecast::class, 1);
        $this->assertSame($data['period_end']->toDateTimeString(), $forecast->period_end->toDateTimeString());
        $this->assertSame($data['pv_estimate'], $forecast->pv_estimate);
        $this->assertSame($data['pv_estimate10'], $forecast->pv_estimate10);
        $this->assertSame($data['pv_estimate90'], $forecast->pv_estimate90);
    }

    public function test_a_forecast_can_be_created_with_utc_iso_8601_date_string(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'period_end' => now('UTC')->parse('2024-06-15T09:00:00.0000000Z'),
            'pv_estimate' => $estimate,
            'pv_estimate10' => $estimate * 0.5,
            'pv_estimate90' => $estimate * 1.2,
        ];
        $forecast = Forecast::create($data);

        $this->assertInstanceOf(Forecast::class, $forecast);
        $this->assertDatabaseCount(Forecast::class, 1);
        $this->assertSame(now()->parse($data['period_end'])->toDateTimeString(), $forecast->period_end->toDateTimeString());
        $this->assertSame($data['pv_estimate'], $forecast->pv_estimate);
        $this->assertSame($data['pv_estimate10'], $forecast->pv_estimate10);
        $this->assertSame($data['pv_estimate90'], $forecast->pv_estimate90);
    }

    public function test_a_forecast_can_not_be_created_for_the_same_period(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $data = [
            'period_end' => now()->addHours(2)->startOfHour(),
            'pv_estimate' => fake()->randomFloat(4),
        ];
        $forecast = Forecast::create($data);

        $this->assertInstanceOf(Forecast::class, $forecast);

        $newData = [
            'period_end' => $data['period_end'],
            'pv_estimate' => fake()->randomFloat(4),
        ];

        // Will throw an exception
        Forecast::create($newData);
    }

    public function test_a_forecast_can_be_upserted_for_the_same_period(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'period_end' => now()->addHours(2)->startOfHour(),
            'pv_estimate' => $estimate,
            'pv_estimate10' => $estimate * 0.5,
            'pv_estimate90' => $estimate * 1.2,
        ];

        $forecast = Forecast::create($data);

        $this->assertInstanceOf(Forecast::class, $forecast);
        $this->assertSame($data['pv_estimate'], $forecast->pv_estimate);
        $this->assertSame($data['pv_estimate10'], $forecast->pv_estimate10);
        $this->assertSame($data['pv_estimate90'], $forecast->pv_estimate90);

        $estimate1 = 2.22;
        $estimate2 = 3.22;

        $newData = [
            [
                'period_end' => $data['period_end'],
                'pv_estimate' => $estimate1,
                'pv_estimate10' => $estimate1 * 0.6,
                'pv_estimate90' => $estimate1 * 1.5,
            ],
            [
                'period_end' => $data['period_end']->clone()->addMinutes(30),
                'pv_estimate' => $estimate2,
                'pv_estimate10' => $estimate2 * 0.6,
                'pv_estimate90' => $estimate2 * 1.5,
            ],
        ];

        $additionalForecast = Forecast::upsert(
            $newData,
            uniqueBy: ['period_end'],
            update: ['pv_estimate', 'pv_estimate10', 'pv_estimate90']
        );

        $this->assertDatabaseCount(Forecast::class, 2);

        $this->assertSame($additionalForecast, 2);

        $forecast->refresh();
        $this->assertInstanceOf(Forecast::class, $forecast);
        $this->assertSame($newData[0]['pv_estimate'], $forecast->pv_estimate);
        $this->assertNotSame($data['pv_estimate'], $forecast->pv_estimate);
        $this->assertNotSame($data['pv_estimate10'], $forecast->pv_estimate10);
        $this->assertNotSame($data['pv_estimate90'], $forecast->pv_estimate90);

        $newForecast = Forecast::wherePeriodEnd($newData[1]['period_end'])->first();
        $this->assertInstanceOf(Forecast::class, $newForecast);
        $this->assertSame($newData[1]['pv_estimate'], $newForecast->pv_estimate);
        $this->assertSame($newData[1]['pv_estimate10'], $newForecast->pv_estimate10);
        $this->assertSame($newData[1]['pv_estimate90'], $newForecast->pv_estimate90);
    }
}
