<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use App\Filament\Resources\StrategyResource;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery as m;
use Tests\TestCase;

final class GenerateActionTest extends TestCase
{
    use RefreshDatabase;

    public function testGenerateActionShowsErrorToastOnFailure(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Seed one strategy record for the current day so the table has rows
        Strategy::factory()->create(['period' => now()->timezone('UTC')->startOfDay()->addHours(12)]);

        /** @var m\MockInterface&CommandBus $bus */
        $bus = m::mock(CommandBus::class);
        // Expect dispatch of GenerateStrategyCommand and return failure with message
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $bus->shouldReceive('dispatch')
            ->once()
            ->with(m::on(fn ($cmd) => $cmd instanceof GenerateStrategyCommand))
            ->andReturn(ActionResult::failure('Strategy generation failed'));
        $this->app->instance(CommandBus::class, $bus);

        Livewire::actingAs($user)
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->callTableAction('Generate strategy')
            ->assertNotified('Strategy generation failed');
    }

    public function testGenerateActionShowsSuccessToastOnSuccess(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Strategy::factory()->create(['period' => now()->timezone('UTC')->startOfDay()->addHours(12)]);

        /** @var m\MockInterface&CommandBus $bus */
        $bus = m::mock(CommandBus::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $bus->shouldReceive('dispatch')
            ->once()
            ->with(m::on(fn ($cmd) => $cmd instanceof GenerateStrategyCommand))
            ->andReturn(ActionResult::success(null, 'Generated'));
        $this->app->instance(CommandBus::class, $bus);

        Livewire::actingAs($user)
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->callTableAction('Generate strategy')
            ->assertNotified('Generated');
    }
}
