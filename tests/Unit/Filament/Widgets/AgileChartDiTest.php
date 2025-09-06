<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Domain\Energy\Actions\OctopusImport;
use App\Filament\Widgets\AgileChart;
use App\Support\Actions\ActionResult;
use Mockery as m;
use Tests\TestCase;

class AgileChartDiTest extends TestCase
{
    public function testUpdateAgileImportResolvesActionsViaContainerAndCallsExecute(): void
    {
        $widget = app(AgileChart::class);
        $ref = new \ReflectionClass(AgileChart::class);
        $method = $ref->getMethod('updateAgileImport');
        $method->setAccessible(true);

        /** @var m\MockInterface&OctopusImport $octopusImport */
        $octopusImport = m::mock(OctopusImport::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $octopusImport->shouldReceive('execute')->once()->andReturn(ActionResult::success());
        $this->app->instance(OctopusImport::class, $octopusImport);

        // Also mock AgileImport action class resolution to avoid network but allow execute without assertions
        /** @var m\MockInterface&\App\Domain\Energy\Actions\AgileImport $agileImportAction */
        $agileImportAction = m::mock(\App\Domain\Energy\Actions\AgileImport::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $agileImportAction->shouldReceive('execute')->once()->andReturn(ActionResult::success());
        $this->app->instance(\App\Domain\Energy\Actions\AgileImport::class, $agileImportAction);

        $method->invoke($widget);
        $this->addToAssertionCount(1);
    }
}
