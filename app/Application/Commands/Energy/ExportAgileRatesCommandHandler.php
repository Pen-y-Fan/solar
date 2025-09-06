<?php

declare(strict_types=1);

namespace App\Application\Commands\Energy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Energy\Actions\AgileExport as AgileExportAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;

final class ExportAgileRatesCommandHandler implements CommandHandler
{
    public function __construct(private readonly AgileExportAction $action)
    {
    }

    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof ExportAgileRatesCommand);
        $start = microtime(true);
        Log::info('ExportAgileRatesCommand started');
        try {
            $result = $this->action->execute();
            if (!$result->isSuccess()) {
                throw new \RuntimeException($result->getMessage() ?? 'Agile export failed');
            }
            Log::info('ExportAgileRatesCommand finished', [
                'success' => true,
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return $result;
        } catch (\Throwable $e) {
            Log::warning('ExportAgileRatesCommand failed', [
                'exception' => $e->getMessage(),
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return ActionResult::failure($e->getMessage());
        }
    }
}
