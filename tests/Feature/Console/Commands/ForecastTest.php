<?php

namespace Tests\Feature\Console\Commands;

use App\Models\ActualForecast;
use App\Models\Forecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecast_console_command(): void
    {
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

        $this->artisan('app:forecast')
            ->expectsOutputToContain('Forecast has been fetched!')
            ->expectsOutputToContain('Actual forecast has been fetched!')
            ->assertSuccessful();
    }
}
