<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Application\Commands\Energy\ImportAgileRatesCommandHandler;
use App\Events\AgileRatesUpdated;
use App\Domain\Energy\Actions\AgileImport as AgileImportAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Event;
use Mockery as m;
use Tests\TestCase;

final class ImportAgileRatesCommandHandlerTest extends TestCase
{
    public function testSuccessPathDelegatesToActionExecuteAndDispatchesEvent(): void
    {
        Event::fake();

        /** @var m\MockInterface&AgileImportAction $action */
        $action = m::mock(AgileImportAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->andReturn(ActionResult::success(['records' => 1], 'Agile import updated'));

        $handler = new ImportAgileRatesCommandHandler($action);
        $result = $handler->handle(new ImportAgileRatesCommand());

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Agile import updated', $result->getMessage());

        Event::assertDispatched(AgileRatesUpdated::class);
    }

    public function testFailureIsWrappedAndReturnedAndDoesNotDispatchEvent(): void
    {
        Event::fake();

        /** @var m\MockInterface&AgileImportAction $action */
        $action = m::mock(AgileImportAction::class);
        // @phpstan-ignore-next-line
        $action->shouldReceive('execute')->once()->andReturn(ActionResult::failure('boom'));

        $handler = new ImportAgileRatesCommandHandler($action);
        $result = $handler->handle(new ImportAgileRatesCommand());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('boom', $result->getMessage());

        Event::assertNotDispatched(AgileRatesUpdated::class);
    }
}
