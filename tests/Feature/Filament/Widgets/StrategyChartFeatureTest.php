<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Queries\Strategy\StrategyManualSeriesQuery;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Unit\Filament\Widgets\FakeStrategyManualSeriesQuery;
use App\Filament\Resources\StrategyResource\Pages\ListStrategies;

final class StrategyChartFeatureTest extends TestCase
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

    public function testStrategyChartRendersWithMockedQuery(): void
    {
        // Seed minimal strategies for the page table
        /** @var Collection<int, Strategy> $strategies */
        $strategies = collect([
            Strategy::factory()->create(['period' => now()->startOfDay()->addMinutes(30)]),
            Strategy::factory()->create(['period' => now()->startOfDay()->addMinutes(60)]),
        ]);

        // Prepare fake query result
        $series = collect([
            [
                'period_end' => CarbonImmutable::parse('2025-01-01 00:30', 'UTC'),
                'import' => 1.0,
                'export' => 0.5,
                'acc_cost' => 0.10,
                'import_accumulative_cost' => 0.10,
                'export_accumulative_cost' => 0.00,
                'battery_percent' => 50,
                'charging' => false,
            ],
            [
                'period_end' => CarbonImmutable::parse('2025-01-01 01:00', 'UTC'),
                'import' => 0.0,
                'export' => 0.8,
                'acc_cost' => 0.05,
                'import_accumulative_cost' => 0.10,
                'export_accumulative_cost' => 0.05,
                'battery_percent' => 55,
                'charging' => true,
            ],
        ]);

        // Bind fake query so the widget resolves it via the container
        $this->app->instance(StrategyManualSeriesQuery::class, new FakeStrategyManualSeriesQuery($series, $strategies));

        // Render the ListStrategies page (which contains the StrategyChart widget)
        Livewire::actingAs($this->user)
            ->test(ListStrategies::class)
            ->assertSuccessful();
    }
}
