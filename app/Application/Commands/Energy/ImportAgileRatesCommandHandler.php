<?php

declare(strict_types=1);

namespace App\Application\Commands\Energy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Energy\Actions\AgileImport as AgileImportAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;

final class ImportAgileRatesCommandHandler implements CommandHandler
{
    public function __construct(private readonly AgileImportAction $action)
    {
    }

    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof ImportAgileRatesCommand);
        $start = microtime(true);
        Log::info('ImportAgileRatesCommand started');
        try {
            $result = $this->action->execute();
            if (!$result->isSuccess()) {
                throw new \RuntimeException($result->getMessage() ?? 'Agile import failed');
            }
            Log::info('ImportAgileRatesCommand finished', [
                'success' => true,
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return $result;
        } catch (\Throwable $e) {
            Log::warning('ImportAgileRatesCommand failed', [
                'exception' => $e->getMessage(),
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return ActionResult::failure($e->getMessage());
        }
    }
}
