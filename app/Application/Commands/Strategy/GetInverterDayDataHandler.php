<?php

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use App\Domain\Solis\Actions\SolisInverterDayDataAction;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;

class GetInverterDayDataHandler implements CommandHandler
{
    public function handle(Command $command): ActionResult
    {
        /** @var GetInverterDayDataCommand $command */
        $action = new SolisInverterDayDataAction($command->date);
        $data = $action->execute();

        if (empty($data)) {
            Log::warning('No inverter data to upsert for date: ' . $command->date);
            return ActionResult::success([], 'No data');
        }

        $repo = app(InverterRepositoryInterface::class);
        $repo->upsertFromSolisData($data);

        Log::info('Upserted ' . count($data) . ' inverter records for date: ' . $command->date);
        return ActionResult::success(['count' => count($data)]);
    }
}
