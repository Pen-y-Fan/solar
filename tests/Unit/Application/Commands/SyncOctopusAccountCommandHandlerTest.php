<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Energy\SyncOctopusAccountCommand;
use App\Application\Commands\Energy\SyncOctopusAccountCommandHandler;
use App\Domain\Energy\Actions\Account as AccountAction;
use App\Support\Actions\ActionResult;
use Mockery as m;
use Tests\TestCase;

final class SyncOctopusAccountCommandHandlerTest extends TestCase
{
    public function testSuccessPathDelegatesToActionExecute(): void
    {
        /** @var m\MockInterface&AccountAction $action */
        $action = m::mock(AccountAction::class);
        // @phpstan-ignore-next-line
        $action->shouldReceive('execute')->once()->andReturn(ActionResult::success(null, 'Octopus account fetched'));

        $handler = new SyncOctopusAccountCommandHandler($action);
        $result = $handler->handle(new SyncOctopusAccountCommand());

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Octopus account fetched', $result->getMessage());
    }

    public function testFailureIsWrappedAndReturned(): void
    {
        /** @var m\MockInterface&AccountAction $action */
        $action = m::mock(AccountAction::class);
        // @phpstan-ignore-next-line
        $action->shouldReceive('execute')->once()->andReturn(ActionResult::failure('boom'));

        $handler = new SyncOctopusAccountCommandHandler($action);
        $result = $handler->handle(new SyncOctopusAccountCommand());

        $this->assertFalse($result->isSuccess());
        $this->assertSame('boom', $result->getMessage());
    }
}
