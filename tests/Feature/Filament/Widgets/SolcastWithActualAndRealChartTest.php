<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\Energy\Models\Inverter;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Widgets\SolcastWithActualAndRealChart;

final class SolcastWithActualAndRealChartTest extends TestCase
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

    public function testWidgetRendersWithMergedDatasetsForSelectedDay(): void
    {
        // Choose a date filter in Europe/London timezone, matching widget expectations
        $dateLondon = now('Europe/London')->startOfDay();
        $filter = $dateLondon->format('Y-m-d');

        // Seed ActualForecast entries at specific half-hour periods during the day (stored as UTC timestamps)
        $t1 = $dateLondon->copy()->addHours(8)->setTimezone('UTC');
        $t2 = $dateLondon->copy()->addHours(9)->setTimezone('UTC');
        $t3 = $dateLondon->copy()->addHours(10)->setTimezone('UTC');

        ActualForecast::create(['period_end' => $t1, 'pv_estimate' => 0.6]);
        ActualForecast::create(['period_end' => $t2, 'pv_estimate' => 0.8]);
        ActualForecast::create(['period_end' => $t3, 'pv_estimate' => 0.4]);

        // Seed Inverter entries; two match ActualForecast times, one intentionally missing to exercise zero fallback
        Inverter::create(['period' => $t1, 'yield' => 0.10]);
        Inverter::create(['period' => $t3, 'yield' => 0.20]);

        // Render the widget with the filter set; assert successful render and heading presence
        Livewire::actingAs($this->user)
            ->test(SolcastWithActualAndRealChart::class)
            ->set('filter', $filter)
            ->assertSuccessful()
            ->assertSee('Solcast actual (0.90) vs PV Yield (0.30) Chart');
    }

    public function testWidgetShowsNoDataHeadingWhenEmpty(): void
    {
        Livewire::actingAs($this->user)
            ->test(SolcastWithActualAndRealChart::class)
            // Set filter to a valid date but do not seed any data
            ->set('filter', now('Europe/London')->format('Y-m-d'))
            ->assertSuccessful()
            ->assertSee(' No data for PV yield vs Solcast actual forecast');
    }
}
