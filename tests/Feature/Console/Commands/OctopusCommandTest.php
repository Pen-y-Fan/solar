<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Energy\ExportAgileRatesCommand;
use App\Application\Commands\Energy\ExportOctopusUsageCommand;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Application\Commands\Energy\ImportOctopusUsageCommand;
use App\Application\Commands\Energy\SyncOctopusAccountCommand;
use App\Events\AgileRatesUpdated;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Event;
use Mockery as m;
use Tests\TestCase;

final class OctopusCommandTest extends TestCase
{
    public function testItDispatchesAllExpectedCommands(): void
    {
        // We use Event::fake() to check if AgileRatesUpdated is dispatched later
        Event::fake([AgileRatesUpdated::class]);

        // We can't easily mock the CommandBus and run the command at the same time
        // because the CommandBus is resolved from the container.
        $bus = m::mock(CommandBus::class);
        $this->app->instance(CommandBus::class, $bus);

        $successResult = ActionResult::success();

        $bus->shouldReceive('dispatch')
            ->with(m::type(SyncOctopusAccountCommand::class))
            ->once()
            ->andReturn($successResult);

        $bus->shouldReceive('dispatch')
            ->with(m::type(ImportOctopusUsageCommand::class))
            ->once()
            ->andReturn($successResult);

        $bus->shouldReceive('dispatch')
            ->with(m::type(ExportOctopusUsageCommand::class))
            ->once()
            ->andReturn($successResult);

        $bus->shouldReceive('dispatch')
            ->with(m::type(ImportAgileRatesCommand::class))
            ->once()
            ->andReturn($successResult);

        $bus->shouldReceive('dispatch')
            ->with(m::type(ExportAgileRatesCommand::class))
            ->once()
            ->andReturn($successResult);

        $this->artisan('app:octopus')
            ->assertExitCode(0);
    }
}
