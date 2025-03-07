<?php

namespace Tests\Feature\Console\Commands;

use App\Models\ActualForecast;
use App\Console\Commands\Forecast as ForecastCommand;
use App\Models\Forecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecast_console_command(): void
    {
        // Arrange
        Http::fake([
            'https://api.solcast.com.au/rooftop_sites/*/forecasts/*' => Http::response([
                'forecasts' => [
                    [
                        'period_end' => now()->addHour()->toISOString(),
                        'pv_estimate' => 0.7,
                        'pv_estimate10' => 0.6,
                        'pv_estimate90' => 0.8,
                    ],
                ],
            ], 200),

            'https://api.solcast.com.au/rooftop_sites/*/estimated_actuals*' => Http::response([
                'estimated_actuals' => [
                    [
                        'period_end' => now()->addHour()->toISOString(),
                        'pv_estimate' => 0.9,
                    ],
                ],
            ], 200),
        ]);

        Log::spy();

        $estimate = fake()->randomFloat(4);
        $forecast = new Forecast();
        $forecast->period_end = now()->subHours(3)->startOfHour();
        $forecast->pv_estimate = $estimate;
        $forecast->pv_estimate10 = $estimate * 0.1;
        $forecast->pv_estimate90 = $estimate * 1.1;
        $forecast->updated_at = now()->subHours(3)->startOfHour();
        $forecast->save();

        $actualForecast = new ActualForecast();
        $actualForecast->period_end = now()->subHours(3)->startOfHour();
        $actualForecast->pv_estimate = $estimate;
        $actualForecast->updated_at = now()->subHours(3)->startOfHour();
        $actualForecast->save();

        // Act & Assert
        $this->artisan((new ForecastCommand)->getName())
            ->expectsOutputToContain('Forecast has been fetched!')
            ->expectsOutputToContain('Actual forecast has been fetched!')
            ->assertSuccessful();

        $this->assertDatabaseHas('forecasts', [
            'pv_estimate' => 0.7,
            'pv_estimate10' => 0.6,
            'pv_estimate90' => 0.8,
        ]);

        $this->assertDatabaseHas('actual_forecasts', [
            'pv_estimate' => 0.9,
        ]);


    }
}
