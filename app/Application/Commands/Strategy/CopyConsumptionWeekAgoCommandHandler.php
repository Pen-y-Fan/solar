<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Strategy\Helpers\DateUtils;
use App\Domain\Strategy\Models\Strategy;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CopyConsumptionWeekAgoCommandHandler implements CommandHandler
{
    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof CopyConsumptionWeekAgoCommand);

        $startedAt = microtime(true);
        Log::info('CopyConsumptionWeekAgoCommand started');

        try {
            [$start, $end] = DateUtils::calculateDateRange($command->date);
            $count = 0;
            $updates = [];

            $strategies = Strategy::query()->whereBetween('period', [$start, $end])->get();

            foreach ($strategies as $strategy) {
                $consumptionData = $strategy->getConsumptionDataValueObject();
                if ($consumptionData->lastWeek !== null) {
                    $updates[] = [
                        'id'                 => $strategy->id,
                        'consumption_manual' => $consumptionData->lastWeek,
                    ];
                    $count++;
                }
            }

            if ($count > 0) {
                DB::transaction(function () use ($updates) {
                    Strategy::upsert($updates, 'id');
                });
            }

            $ms = (int)((microtime(true) - $startedAt) * 1000);
            Log::info('CopyConsumptionWeekAgoCommand finished', [
                'success' => true,
                'ms'      => $ms,
                'count'   => $count,
            ]);

            return ActionResult::success(null, "Copied consumption for {$count} strategy rows");
        } catch (\Throwable $e) {
            $ms = (int)((microtime(true) - $startedAt) * 1000);
            Log::warning('CopyConsumptionWeekAgoCommand failed', [
                'exception' => $e->getMessage(),
                'ms'        => $ms,
            ]);

            return ActionResult::failure('Copy consumption failed: ' . $e->getMessage());
        }
    }
}
