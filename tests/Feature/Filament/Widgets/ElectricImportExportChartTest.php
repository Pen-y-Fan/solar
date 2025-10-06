<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Queries\Energy\ElectricImportExportSeriesQuery;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Resources\StrategyResource\Pages\ListStrategies;
use Illuminate\Support\Carbon;

final class ElectricImportExportChartTest extends TestCase
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

    public function testElectricImportExportChartRendersWithMockedQuery(): void
    {
        // Seed minimal strategies for the page table (widget reads via query, but page needs records)
        Strategy::factory()->create(['period' => now()->startOfDay()->addMinutes(30)]);
        Strategy::factory()->create(['period' => now()->startOfDay()->addMinutes(60)]);

        $start = Carbon::parse('2025-01-01 00:00:00', 'UTC');

        // Prepare fake query result collection matching widget expectations
        $series = collect([
            [
                'interval_start' => $start->copy()->toIso8601String(),
                'interval_end' => $start->copy()->addMinutes(30)->toIso8601String(),
                'export_consumption' => -0.5, // widget negates export for display
                'export_accumulative_cost' => -0.10,
                'import_consumption' => 0.2,
                'import_accumulative_cost' => 0.05,
                'net_accumulative_cost' => -0.05,
                'battery_percent' => 55,
            ],
            [
                'interval_start' => $start->copy()->addMinutes(30)->toIso8601String(),
                'interval_end' => $start->copy()->addMinutes(60)->toIso8601String(),
                'export_consumption' => -0.3,
                'export_accumulative_cost' => -0.20,
                'import_consumption' => 0.4,
                'import_accumulative_cost' => 0.15,
                'net_accumulative_cost' => -0.05,
                'battery_percent' => 52,
            ],
        ]);

        // Simple fake query class implementing the expected run signature
        $fake = new class ($series) {
            public function __construct(private Collection $ret)
            {
            }

            public function run($start, $limit): Collection
            {
                return $this->ret;
            }
        };

        // Bind fake into container
        $this->app->instance(ElectricImportExportSeriesQuery::class, $fake);

        Livewire::actingAs($this->user)
            ->test(ListStrategies::class)
            ->assertSuccessful();
    }
}
