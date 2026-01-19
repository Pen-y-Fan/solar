<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\CalculateBatteryCommand;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use App\Filament\Resources\StrategyResource;
use App\Support\Actions\ActionResult;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery as m;
use Tests\TestCase;

final class CalculateBatteryActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);

        Carbon::setTestNow('2025-11-20 10:00:00');
        // Ensure the table has at least 21 rows
        $strategy = Strategy::factory()->create(['period' => now('UTC')]);
        $periods = new CarbonPeriod(now()->addMinutes(5), '5 minutes', 20);
        $strategyArray = $strategy->toArray();
        unset($strategyArray['id']);

        $updates = [];
        foreach ($periods as $period) {
            $strategyArray['period'] = $period;
            $updates[] = $strategyArray;
        }
        Strategy::insert($updates);
    }

    public function testCalculateBatteryActionShowsErrorToastOnFailure(): void
    {
        /** @var m\MockInterface&CommandBus $bus */
        $bus = m::mock(CommandBus::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $bus->shouldReceive('dispatch')
            ->once()
            ->with(m::on(fn ($cmd) => $cmd instanceof CalculateBatteryCommand))
            ->andReturn(ActionResult::failure('Battery calculation failed'));
        $this->app->instance(CommandBus::class, $bus);

        Livewire::actingAs(auth()->user())
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->callTableAction('Calculate battery')
            ->assertNotified('Battery calculation failed');
    }

    public function testCalculateBatteryActionShowsSuccessToastOnSuccess(): void
    {
        /** @var m\MockInterface&CommandBus $bus */
        $bus = m::mock(CommandBus::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $bus->shouldReceive('dispatch')
            ->once()
            ->with(m::on(fn ($cmd) => $cmd instanceof CalculateBatteryCommand))
            ->andReturn(ActionResult::success(null, 'Calculated'));
        $this->app->instance(CommandBus::class, $bus);

        Livewire::actingAs(auth()->user())
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->callTableAction('Calculate battery')
            ->assertNotified('Calculated');
    }
}
