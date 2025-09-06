<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Application\Commands\Strategy\GenerateStrategyCommandHandler;
use App\Domain\Strategy\Actions\GenerateStrategyAction;
use App\Support\Actions\ActionResult;
use Mockery as m;
use Tests\TestCase;

final class GenerateStrategyCommandHandlerTest extends TestCase
{
    public function testValidationFailureReturnsFailureResult(): void
    {
        $action = m::mock(GenerateStrategyAction::class);
        // @phpstan-ignore-next-line Passing a mock that implements the same interface is acceptable in tests
        $handler = new GenerateStrategyCommandHandler($action);

        $result = $handler->handle(new GenerateStrategyCommand(period: ''));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Invalid period', $result->getMessage());
    }

    public function testSuccessPathDelegatesToActionExecute(): void
    {
        /** @var m\MockInterface&GenerateStrategyAction $action */
        $action = m::mock(GenerateStrategyAction::class);
        // @phpstan-ignore-next-line Mockery dynamic expectation count method
        $action->shouldReceive('execute')->once()->andReturn(ActionResult::success(null, 'Strategy generated'));

        $handler = new GenerateStrategyCommandHandler($action);

        $result = $handler->handle(new GenerateStrategyCommand(period: 'today'));

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Strategy generated', $result->getMessage());
    }
}
