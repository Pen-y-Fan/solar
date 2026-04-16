<?php

declare(strict_types=1);

namespace App\Application\Commands\Energy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Energy\Actions\OctopusExport as OctopusExportAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

use function assert;

final readonly class ExportOctopusUsageCommandHandler implements CommandHandler
{
    public function __construct(private OctopusExportAction $action)
    {
    }

    public function handle(Command $command): ActionResult
    {
        assert($command instanceof ExportOctopusUsageCommand);
        $start = microtime(true);
        Log::info('ExportOctopusUsageCommand started');
        try {
            $result = $this->action->execute();
            if (!$result->isSuccess()) {
                throw new RuntimeException($result->getMessage() ?? 'Octopus usage export failed');
            }
            Log::info('ExportOctopusUsageCommand finished', [
                'success' => true,
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return $result;
        } catch (Throwable $e) {
            Log::warning('ExportOctopusUsageCommand failed', [
                'exception' => $e->getMessage(),
                'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return ActionResult::failure($e->getMessage());
        }
    }
}
