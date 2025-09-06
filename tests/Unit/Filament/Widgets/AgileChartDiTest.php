<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Domain\Energy\Actions\OctopusImport;
use App\Filament\Widgets\AgileChart;
use App\Support\Actions\ActionResult;
use Mockery as m;
use Tests\TestCase;

class AgileChartDiTest extends TestCase
{
    public function testUpdateAgileImportUsesCommandBusAndOctopusImport(): void
    {
        $widget = app(AgileChart::class);
        $ref = new \ReflectionClass(AgileChart::class);
        $method = $ref->getMethod('updateAgileImport');
        $method->setAccessible(true);

        /** @var m\MockInterface&CommandBus $bus */
        $bus = m::mock(CommandBus::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $bus->shouldReceive('dispatch')
            ->once()
            ->with(m::on(fn($cmd) => $cmd instanceof ImportAgileRatesCommand))
            ->andReturn(ActionResult::success());
        $this->app->instance(CommandBus::class, $bus);

        /** @var m\MockInterface&OctopusImport $octopusImport */
        $octopusImport = m::mock(OctopusImport::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $octopusImport->shouldReceive('execute')->once()->andReturn(ActionResult::success());
        $this->app->instance(OctopusImport::class, $octopusImport);

        $method->invoke($widget);
        $this->addToAssertionCount(1);
    }
}
