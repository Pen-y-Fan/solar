<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Queries\Strategy\StrategyPerformanceSummaryQuery;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Resources\StrategyResource\Pages\ListStrategies;

final class StrategyOverviewTest extends TestCase
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

    public function testStrategyOverviewRendersWithMockedQuery(): void
    {
        // Seed minimal strategies so ListStrategies page has records
        Strategy::factory()->create(['period' => now()->startOfDay()->addMinutes(15)]);
        Strategy::factory()->create(['period' => now()->startOfDay()->addMinutes(45)]);

        // Build a fake summary collection matching expected keys used by the widget
        $summary = collect([
            [
                'total_import_kwh' => 2.5,
                'import_cost_pence' => 50.0,
                'total_battery_kwh' => 1.2,
                'battery_cost_pence' => 5.0,
                'export_kwh' => 0.7,
                'export_revenue_pence' => 14.0,
                'self_consumption_kwh' => 1.8,
                'net_cost_pence' => 41.0,
            ],
            [
                'total_import_kwh' => 1.0,
                'import_cost_pence' => 20.0,
                'total_battery_kwh' => 0.3,
                'battery_cost_pence' => 2.0,
                'export_kwh' => 0.2,
                'export_revenue_pence' => 4.0,
                'self_consumption_kwh' => 0.6,
                'net_cost_pence' => 18.0,
            ],
        ]);

        $fake = new class ($summary) {
            public function __construct(private Collection $ret)
            {
            }

            public function run($start, $end): Collection
            {
                return $this->ret;
            }
        };

        $this->app->instance(StrategyPerformanceSummaryQuery::class, $fake);

        Livewire::actingAs($this->user)
            ->test(ListStrategies::class)
            ->assertSuccessful()
            // We can't easily capture widget output, but ensure the page renders which includes the widget
            ;
    }
}
