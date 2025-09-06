<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Energy\ExportAgileRatesCommand;
use App\Application\Commands\Energy\ExportAgileRatesCommandHandler;
use App\Domain\Energy\Actions\AgileExport as AgileExportAction;
use App\Support\Actions\ActionResult;
use Mockery as m;
use Tests\TestCase;

final class ExportAgileRatesCommandHandlerTest extends TestCase
{
    public function testSuccessPathDelegatesToActionExecute(): void
    {
        /** @var m\MockInterface&AgileExportAction $action */
        $action = m::mock(AgileExportAction::class);
        // @phpstan-ignore-next-line
        $action->shouldReceive('execute')
            ->once()
            ->andReturn(ActionResult::success(['records' => 1], 'Agile export updated'));

        $handler = new ExportAgileRatesCommandHandler($action);
        $result = $handler->handle(new ExportAgileRatesCommand());

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Agile export updated', $result->getMessage());
    }

    public function testFailureIsWrappedAndReturned(): void
    {
        /** @var m\MockInterface&AgileExportAction $action */
        $action = m::mock(AgileExportAction::class);
        // @phpstan-ignore-next-line
        $action->shouldReceive('execute')->once()->andReturn(ActionResult::failure('boom'));

        $handler = new ExportAgileRatesCommandHandler($action);
        $result = $handler->handle(new ExportAgileRatesCommand());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('boom', $result->getMessage());
    }
}
