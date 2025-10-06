<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\CalculateBatteryCommand;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use App\Filament\Resources\StrategyResource;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery as m;
use Tests\TestCase;

final class CalculateBatteryActionTest extends TestCase
{
    use RefreshDatabase;

    public function testCalculateBatteryActionShowsErrorToastOnFailure(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Ensure the table has at least one row
        Strategy::factory()->create(['period' => now()->timezone('UTC')->startOfDay()->addHours(12)]);

        /** @var m\MockInterface&CommandBus $bus */
        $bus = m::mock(CommandBus::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $bus->shouldReceive('dispatch')
            ->once()
            ->with(m::on(fn ($cmd) => $cmd instanceof CalculateBatteryCommand))
            ->andReturn(ActionResult::failure('Battery calculation failed'));
        $this->app->instance(CommandBus::class, $bus);

        Livewire::actingAs($user)
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->callTableAction('Calculate battery')
            ->assertNotified('Battery calculation failed');
    }

    public function testCalculateBatteryActionShowsSuccessToastOnSuccess(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Strategy::factory()->create(['period' => now()->timezone('UTC')->startOfDay()->addHours(12)]);

        /** @var m\MockInterface&CommandBus $bus */
        $bus = m::mock(CommandBus::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $bus->shouldReceive('dispatch')
            ->once()
            ->with(m::on(fn ($cmd) => $cmd instanceof CalculateBatteryCommand))
            ->andReturn(ActionResult::success(null, 'Calculated'));
        $this->app->instance(CommandBus::class, $bus);

        Livewire::actingAs($user)
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->callTableAction('Calculate battery')
            ->assertNotified('Calculated');
    }
}
