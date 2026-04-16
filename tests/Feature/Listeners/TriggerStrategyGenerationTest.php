<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Domain\Energy\Models\AgileImport;
use App\Events\AgileRatesUpdated;
use App\Listeners\TriggerStrategyGeneration;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery as m;
use Tests\TestCase;

final class TriggerStrategyGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function testItDispatchesGenerateStrategyCommandsWhenDataIsAvailable(): void
    {
        Carbon::setTestNow('2026-04-16 17:00:00'); // Thursday 5pm

        /** @var m\MockInterface&CommandBus $commandBus */
        $commandBus = m::mock(CommandBus::class);

        // Expect two dispatches: today 4pm and tomorrow 4pm
        $commandBus->shouldReceive('dispatch')
            ->with(m::on(fn($command) => $command instanceof GenerateStrategyCommand
                && $command->period === '2026-04-16 16:00'))
            ->once()
            ->andReturn(ActionResult::success());

        $commandBus->shouldReceive('dispatch')
            ->with(m::on(fn($command) => $command instanceof GenerateStrategyCommand
                && $command->period === '2026-04-17 16:00'))
            ->once()
            ->andReturn(ActionResult::success());

        // Seed AgileImport to have data until tomorrow 4pm + 24h = 18th 4pm
        AgileImport::factory()->create(['valid_to' => '2026-04-18 16:00:00']);

        $listener = new TriggerStrategyGeneration($commandBus);
        $listener->handle(new AgileRatesUpdated());
    }

    public function testItOnlyDispatchesTodayWhenTomorrowDataIsMissing(): void
    {
        Carbon::setTestNow('2026-04-16 17:00:00'); // Thursday 5pm

        /** @var m\MockInterface&CommandBus $commandBus */
        $commandBus = m::mock(CommandBus::class);

        // Expect one dispatch: today 4pm
        $commandBus->shouldReceive('dispatch')
            ->with(m::on(fn($command) => $command instanceof GenerateStrategyCommand
                && $command->period === '2026-04-16 16:00'))
            ->once()
            ->andReturn(ActionResult::success());

        $commandBus->shouldNotReceive('dispatch')
            ->with(m::on(fn($command) => $command instanceof GenerateStrategyCommand
                && $command->period === '2026-04-17 16:00'));

        // Seed AgileImport to have data only until 17th 4pm
        AgileImport::factory()->create(['valid_to' => '2026-04-17 16:00:00']);

        $listener = new TriggerStrategyGeneration($commandBus);
        $listener->handle(new AgileRatesUpdated());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
