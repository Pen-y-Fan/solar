<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\Forecast as ForecastCommand;
use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\Forecasting\Models\Forecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    public function testForecastConsoleCommand(): void
    {
        // Arrange
        // Mock Solcast configuration to provide required API credentials
        config([
            'solcast.api_key' => 'test-api-key',
            'solcast.resource_id' => 'test-resource-id'
        ]);

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
        $forecast->period_end = now()->subDay()->startOfHour();
        $forecast->pv_estimate = $estimate;
        $forecast->pv_estimate10 = $estimate * 0.1;
        $forecast->pv_estimate90 = $estimate * 1.1;
        $forecast->updated_at = now()->subDay()->startOfHour();
        $forecast->save();

        $actualForecast = new ActualForecast();
        $actualForecast->period_end = now()->subDay()->startOfHour();
        $actualForecast->pv_estimate = $estimate;
        $actualForecast->updated_at = now()->subDay()->startOfHour();
        $actualForecast->save();

        // Act & Assert
        $this->artisan((new ForecastCommand())->getName())
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
