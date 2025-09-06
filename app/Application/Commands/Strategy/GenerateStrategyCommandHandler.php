<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Strategy\Actions\GenerateStrategyAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Event;
use App\Domain\Strategy\Events\StrategyGenerated;

final class GenerateStrategyCommandHandler implements CommandHandler
{
    public function __construct(private readonly GenerateStrategyAction $action)
    {
    }

    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof GenerateStrategyCommand);

        $validator = Validator::make(['period' => $command->period], [
            'period' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return ActionResult::failure('Invalid period', 'validation_failed');
        }

        $start = microtime(true);
        Log::info('GenerateStrategyCommand started', ['period' => $command->period]);

        try {
            $result = DB::transaction(function () use ($command) {
                $this->action->filter = $command->period;
                $result = $this->action->execute();
                if (!$result->isSuccess()) {
                    throw new \RuntimeException($result->getMessage() ?? 'Strategy generation failed');
                }
                // Emit domain event for observability/integration
                Event::dispatch(new StrategyGenerated($command->period));
                return $result;
            });

            Log::info('GenerateStrategyCommand finished', [
                'period' => $command->period,
                'success' => true,
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('GenerateStrategyCommand failed', [
                'period' => $command->period,
                'exception' => $e->getMessage(),
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return ActionResult::failure($e->getMessage());
        }
    }
}
