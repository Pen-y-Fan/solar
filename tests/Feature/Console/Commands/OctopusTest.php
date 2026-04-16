<?php

namespace Tests\Feature\Console\Commands;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Energy\ExportAgileRatesCommand;
use App\Application\Commands\Energy\ExportOctopusUsageCommand;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Application\Commands\Energy\ImportOctopusUsageCommand;
use App\Application\Commands\Energy\SyncOctopusAccountCommand;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as m;
use Tests\TestCase;

class OctopusTest extends TestCase
{
    use RefreshDatabase;

    public function testOctopusCommandRunsAllActionsAndOutputsMessages(): void
    {
        $commandBus = m::mock(CommandBus::class);
        $this->app->instance(CommandBus::class, $commandBus);

        $commandBus->shouldReceive('dispatch')
            ->with(m::type(SyncOctopusAccountCommand::class))
            ->once()
            ->andReturn(ActionResult::success(null, 'Octopus account synced'));

        $commandBus->shouldReceive('dispatch')
            ->with(m::type(ImportOctopusUsageCommand::class))
            ->once()
            ->andReturn(ActionResult::success(null, 'Octopus usage imported'));

        $commandBus->shouldReceive('dispatch')
            ->with(m::type(ExportOctopusUsageCommand::class))
            ->once()
            ->andReturn(ActionResult::success(null, 'Octopus usage exported'));

        $commandBus->shouldReceive('dispatch')
            ->with(m::type(ImportAgileRatesCommand::class))
            ->once()
            ->andReturn(ActionResult::success(null, 'Octopus Agile import successful'));

        $commandBus->shouldReceive('dispatch')
            ->with(m::type(ExportAgileRatesCommand::class))
            ->once()
            ->andReturn(ActionResult::success(null, 'Octopus Agile export successful'));

        $this->artisan('app:octopus')
            ->expectsOutputToContain('Running Octopus action!')
            ->expectsOutputToContain('Octopus account sync successful: Octopus account synced')
            ->expectsOutputToContain('Octopus usage import successful: Octopus usage imported')
            ->expectsOutputToContain('Octopus usage export successful: Octopus usage exported')
            ->expectsOutputToContain('Octopus Agile import successful: Octopus Agile import successful')
            ->expectsOutputToContain('Octopus Agile export successful: Octopus Agile export successful')
            ->assertSuccessful();
    }
}
