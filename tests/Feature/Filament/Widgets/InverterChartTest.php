<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Queries\Energy\InverterConsumptionRangeQuery;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Widgets\InverterChart;

final class InverterChartTest extends TestCase
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

    public function testInverterChartRendersWithMockedQuery(): void
    {
        // Prepare fake query result collection: 30-minute buckets over 2 hours
        $series = collect([
            ['time' => '00:00:00', 'value' => 0.10],
            ['time' => '00:30:00', 'value' => 0.20],
            ['time' => '01:00:00', 'value' => 0.15],
            ['time' => '01:30:00', 'value' => 0.05],
        ]);

        // Simple fake query class that returns the prepared collection regardless of inputs
        $fake = new class ($series)
        {
            public function __construct(private Collection $ret)
            {
            }

            public function run($startUtc, $endUtc, int $points): Collection
            {
                return $this->ret;
            }
        };

        // Bind fake into the container so the widget resolves it
        $this->app->instance(InverterConsumptionRangeQuery::class, $fake);

        Livewire::actingAs($this->user)
            ->test(InverterChart::class)
            ->assertSuccessful();
    }
}
