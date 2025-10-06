<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Forecasting\Models\Forecast;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Widgets\SolcastForecastChart;

final class SolcastForecastChartTest extends TestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user);
    }

    public function testSolcastForecastChartRendersWithSeededForecasts(): void
    {
        // Seed minimal forecast data covering the next few hours so the widget has data to plot
        Forecast::factory()->count(4)->create();

        // Ensure updated_at is recent to avoid triggering a refresh via ForecastAction
        Forecast::query()->update(['updated_at' => now()]);

        Livewire::actingAs($this->user)
            ->test(SolcastForecastChart::class)
            ->assertSuccessful();
    }
}
