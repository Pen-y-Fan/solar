<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Forecasting\Actions\ActualForecastAction;
use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Widgets\SolcastActualChart;

final class SolcastActualChartTest extends TestCase
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

    public function testSolcastActualChartTriggersUpdateWhenNoData(): void
    {
        $calls = (object) ['count' => 0];

        // Bind a fake action to observe calls
        $fake = new class ($calls) {
            public function __construct(private object $calls)
            {
            }

            public function execute(): void
            {
                $this->calls->count++;
            }
        };
        // Since ActualForecastAction is a concrete class, bind by class string to our fake that implements same API
        $this->app->instance(ActualForecastAction::class, $fake);

        Livewire::actingAs($this->user)
            ->test(SolcastActualChart::class)
            ->assertSuccessful();

        $this->assertSame(1, $calls->count, 'ActualForecastAction::execute should be called once when no data exists');
    }

    public function testSolcastActualChartRendersWithSeededDataWithoutUpdate(): void
    {
        // Seed a few ActualForecast records within the last day with recent updated_at
        $base = now()->startOfHour();
        $rows = [
            ['period_end' => $base->clone()->subHours(3), 'pv_estimate' => 0.5],
            ['period_end' => $base->clone()->subHours(2), 'pv_estimate' => 0.7],
            ['period_end' => $base->clone()->subHours(1), 'pv_estimate' => 0.4],
            ['period_end' => $base->clone()->addHour(), 'pv_estimate' => 0.6],
        ];
        foreach ($rows as $r) {
            ActualForecast::create($r);
        }
        // Ensure records are considered fresh to avoid triggering update
        ActualForecast::query()->update(['updated_at' => now()]);

        // Bind a fake action that would fail the test if called
        $fake = new class () {
            public function execute(): void
            {
                throw new \RuntimeException('ActualForecastAction should not be called for fresh data');
            }
        };
        $this->app->instance(ActualForecastAction::class, $fake);

        Livewire::actingAs($this->user)
            ->test(SolcastActualChart::class)
            ->assertSuccessful();
    }
}
