<?php

declare(strict_types=1);

namespace App\Application\Commands\Energy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Energy\Actions\Account as AccountAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;

final class SyncOctopusAccountCommandHandler implements CommandHandler
{
    public function __construct(private readonly AccountAction $action)
    {
    }

    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof SyncOctopusAccountCommand);
        $start = microtime(true);
        Log::info('SyncOctopusAccountCommand started');
        try {
            $result = $this->action->execute();
            if (!$result->isSuccess()) {
                throw new \RuntimeException($result->getMessage() ?? 'Account sync failed');
            }
            Log::info('SyncOctopusAccountCommand finished', [
                'success' => true,
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return $result;
        } catch (\Throwable $e) {
            Log::warning('SyncOctopusAccountCommand failed', [
                'exception' => $e->getMessage(),
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return ActionResult::failure($e->getMessage());
        }
    }
}
