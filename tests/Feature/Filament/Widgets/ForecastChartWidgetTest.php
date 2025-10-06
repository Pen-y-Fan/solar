<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Forecasting\Models\Forecast;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Resources\ForecastResource\Pages\ListForecasts;

final class ForecastChartWidgetTest extends TestCase
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

    public function testForecastChartWidgetRenders(): void
    {
        // Seed a handful of Forecast records within the widget's window (now .. +3 days)
        // Factory uses consecutive hours from now, which fits the window.
        Forecast::factory()->count(6)->create();

        Livewire::actingAs($this->user)
            ->test(ListForecasts::class)
            ->assertSuccessful();
    }
}
