<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Application\Commands\Energy\ExportAgileRatesCommand;
use App\Application\Commands\Energy\ExportAgileRatesCommandHandler;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Application\Commands\Energy\ImportAgileRatesCommandHandler;
use App\Application\Commands\Energy\SyncOctopusAccountCommand;
use App\Application\Commands\Energy\SyncOctopusAccountCommandHandler;
use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Application\Commands\Strategy\GenerateStrategyCommandHandler;
use App\Application\Commands\Forecasting\RefreshForecastsCommand;
use App\Application\Commands\Forecasting\RefreshForecastsCommandHandler;
use App\Application\Commands\Strategy\RecalculateStrategyCostsCommand;
use App\Application\Commands\Strategy\RecalculateStrategyCostsCommandHandler;
use App\Support\Actions\ActionResult;
use Tests\TestCase;

final class CommandBusMappingsTest extends TestCase
{
    public function testCommandBusHasMappingsForCoreCommands(): void
    {
        // Bind lightweight fake handler instances for each handler class the AppServiceProvider registers.
        // This ensures dispatch works without invoking heavy domain logic, while verifying the mapping wiring.
        $fakeHandler = new class implements CommandHandler {
            public function handle(Command $command): ActionResult
            {
                return ActionResult::success();
            }
        };

        // Override each concrete handler class with the fake instance
        $this->app->instance(GenerateStrategyCommandHandler::class, $fakeHandler);
        $this->app->instance(ImportAgileRatesCommandHandler::class, $fakeHandler);
        $this->app->instance(ExportAgileRatesCommandHandler::class, $fakeHandler);
        $this->app->instance(SyncOctopusAccountCommandHandler::class, $fakeHandler);
        $this->app->instance(RefreshForecastsCommandHandler::class, $fakeHandler);
        $this->app->instance(RecalculateStrategyCostsCommandHandler::class, $fakeHandler);

        /** @var CommandBus $bus */
        $bus = app(CommandBus::class);

        // Dispatch each core command; if a mapping is missing or wrong,
        // an exception would be thrown or not return ActionResult
        $result1 = $bus->dispatch(new GenerateStrategyCommand(period: 'today'));
        $result2 = $bus->dispatch(new ImportAgileRatesCommand());
        $result3 = $bus->dispatch(new ExportAgileRatesCommand());
        $result4 = $bus->dispatch(new SyncOctopusAccountCommand());
        $result5 = $bus->dispatch(new RefreshForecastsCommand());
        $result6 = $bus->dispatch(new RecalculateStrategyCostsCommand());

        $this->assertInstanceOf(ActionResult::class, $result1);
        $this->assertInstanceOf(ActionResult::class, $result2);
        $this->assertInstanceOf(ActionResult::class, $result3);
        $this->assertInstanceOf(ActionResult::class, $result4);

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
        $this->assertTrue($result3->isSuccess());
        $this->assertTrue($result4->isSuccess());
        $this->assertTrue($result5->isSuccess());
        $this->assertTrue($result6->isSuccess());
    }
}
