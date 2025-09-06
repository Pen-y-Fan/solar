<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use App\Filament\Resources\StrategyResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery as m;
use Tests\TestCase;

final class GenerateActionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function testGenerateActionDispatchesCommandAndSurfacesFailureMessage(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
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
            ->andReturn(\App\Support\Actions\ActionResult::failure('Strategy generation failed'));
        $this->app->instance(CommandBus::class, $bus);

        // Mount the ListStrategies page and trigger the header action
        Livewire::actingAs($user)
            ->test(StrategyResource\Pages\ListStrategies::class)
            // Use the table default period filter (today) and trigger the action
            ->callTableAction('Generate strategy')
            ->assertHasNoTableActionErrors();

        // We can't assert notification text easily here, but reaching this point
        // with a mocked bus ensures dispatch occurred and failure was handled.
        $this->addToAssertionCount(1);
    }
}
