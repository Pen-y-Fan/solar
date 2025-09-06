<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Application\Commands\Strategy\GenerateStrategyCommandHandler;
use App\Domain\Strategy\Actions\GenerateStrategyAction;
use App\Domain\Strategy\Events\StrategyGenerated;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Event;
use Mockery as m;
use Tests\TestCase;

final class GenerateStrategyCommandEmitsEventTest extends TestCase
{
    public function testItEmitsStrategyGeneratedOnSuccess(): void
    {
        Event::fake();

        /** @var m\MockInterface&GenerateStrategyAction $action */
        $action = m::mock(GenerateStrategyAction::class);
        // @phpstan-ignore-next-line
        $action->shouldReceive('execute')->once()->andReturn(ActionResult::success(null, 'ok'));

        $handler = new GenerateStrategyCommandHandler($action);
        $handler->handle(new GenerateStrategyCommand(period: 'today'));

        Event::assertDispatched(StrategyGenerated::class, function (StrategyGenerated $event): bool {
            return $event->period === 'today';
        });
    }
}
