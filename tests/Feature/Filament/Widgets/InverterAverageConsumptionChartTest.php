<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Queries\Energy\InverterConsumptionByTimeQuery;
use App\Domain\User\Models\User;
use App\Filament\Widgets\InverterAverageConsumptionChart;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

final class InverterAverageConsumptionChartTest extends TestCase
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

    public function testWidgetRendersWithMockedInverterConsumptionByTimeQuery(): void
    {
        $series = collect([
            ['time' => '00:00:00', 'value' => 0.05],
            ['time' => '00:30:00', 'value' => 0.10],
            ['time' => '01:00:00', 'value' => 0.20],
            ['time' => '01:30:00', 'value' => 0.15],
        ]);

        $fake = new class ($series)
        {
            public function __construct(private Collection $ret)
            {
            }

            public function run($startDate): Collection
            {
                return $this->ret;
            }
        };

        $this->app->instance(InverterConsumptionByTimeQuery::class, $fake);

        Livewire::actingAs($this->user)
            ->test(InverterAverageConsumptionChart::class)
            ->assertSuccessful()
            ->assertSet('count', 1);
    }
}
