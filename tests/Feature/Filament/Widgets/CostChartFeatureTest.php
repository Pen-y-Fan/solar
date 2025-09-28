<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Queries\Energy\EnergyCostBreakdownByDayQuery;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Resources\StrategyResource\Pages\ListStrategies;

final class CostChartFeatureTest extends TestCase
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

    public function testCostChartRendersWithMockedQuery(): void
    {
        // Seed minimal strategies for the page table (widget reads via query, but page needs records)
        Strategy::factory()->create(['period' => now()->startOfDay()->addMinutes(30)]);
        Strategy::factory()->create(['period' => now()->startOfDay()->addMinutes(60)]);

        // Prepare fake query result collection
        $series = collect([
            [
                'valid_from' => now()->startOfDay()->toIso8601String(),
                'import_value_inc_vat' => 12.34,
                'export_value_inc_vat' => 3.21,
                'net_cost' => 9.13,
            ],
            [
                'valid_from' => now()->startOfDay()->addMinutes(30)->toIso8601String(),
                'import_value_inc_vat' => 10.00,
                'export_value_inc_vat' => 1.00,
                'net_cost' => 9.00,
            ],
        ]);

        // Simple fake query class
        $fake = new class ($series) {
            public function __construct(private Collection $ret)
            {
            }

            public function run($arg): Collection
            {
                return $this->ret;
            }
        };

        // Bind fake into container
        $this->app->instance(EnergyCostBreakdownByDayQuery::class, $fake);

        Livewire::actingAs($this->user)
            ->test(ListStrategies::class)
            ->assertSuccessful();
    }
}
