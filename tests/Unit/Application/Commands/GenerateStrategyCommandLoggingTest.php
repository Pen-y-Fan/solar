<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Application\Commands\Strategy\GenerateStrategyCommandHandler;
use App\Domain\Strategy\Actions\GenerateStrategyAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use Tests\TestCase;

final class GenerateStrategyCommandLoggingTest extends TestCase
{
    public function testItLogsStartAndFinishOnSuccess(): void
    {
        Log::spy();

        /** @var m\MockInterface&GenerateStrategyAction $action */
        $action = m::mock(GenerateStrategyAction::class);
        // @phpstan-ignore-next-line mock expectation
        $action->shouldReceive('execute')->once()->andReturn(ActionResult::success(null, 'ok'));

        $handler = new GenerateStrategyCommandHandler($action);
        $result = $handler->handle(new GenerateStrategyCommand(period: 'today'));

        $this->assertTrue($result->isSuccess());

        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')
            ->with('GenerateStrategyCommand started', m::subset(['period' => 'today']))
            ->once();

        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')
            ->with('GenerateStrategyCommand finished', m::subset(['period' => 'today', 'success' => true,]))
            ->once();
    }
}
