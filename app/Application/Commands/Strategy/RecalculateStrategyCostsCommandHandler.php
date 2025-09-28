<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Strategy\Models\Strategy;
use App\Support\Actions\ActionResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RecalculateStrategyCostsCommandHandler implements CommandHandler
{
    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof RecalculateStrategyCostsCommand);

        $startedAt = microtime(true);
        Log::info('RecalculateStrategyCostsCommand started', [
            'from' => $command->dateFrom,
            'to' => $command->dateTo,
        ]);

        try {
            // Determine range in Europe/London then convert to UTC
            $fromStr = $command->dateFrom;
            $toStr = $command->dateTo;

            if ($fromStr === null && $toStr === null) {
                $fromStr = Carbon::now('Europe/London')->format('Y-m-d');
            }
            if ($toStr === null) {
                $toStr = $fromStr;
            }

            $start = Carbon::parse($fromStr, 'Europe/London')->startOfDay()->timezone('UTC');
            $end = Carbon::parse($toStr, 'Europe/London')->endOfDay()->timezone('UTC');

            $updated = 0;

            DB::transaction(function () use (&$updated, $start, $end) {
                $strategies = Strategy::query()
                    ->whereBetween('period', [$start, $end])
                    ->get();

                foreach ($strategies as $strategy) {
                    $cost = $strategy->getCostDataValueObject();
                    $consumption = $strategy->getConsumptionDataValueObject();

                    $import = $cost->importValueIncVat; // nullable
                    $avg = $consumption->average;       // nullable
                    $last = $consumption->lastWeek;     // nullable

                    $avgCost = ($import !== null && $avg !== null) ? max(0.0, (float)$avg) * (float)$import : null;
                    $lastCost = ($import !== null && $last !== null) ? max(0.0, (float)$last) * (float)$import : null;

                    // Only persist if changes are needed to avoid unnecessary writes
                    $dirty = false;
                    if ($strategy->consumption_average_cost !== $avgCost) {
                        $strategy->consumption_average_cost = $avgCost;
                        $dirty = true;
                    }
                    if ($strategy->consumption_last_week_cost !== $lastCost) {
                        $strategy->consumption_last_week_cost = $lastCost;
                        $dirty = true;
                    }

                    if ($dirty) {
                        $strategy->save();
                        $updated++;
                    }
                }
            });

            $ms = (int)((microtime(true) - $startedAt) * 1000);
            Log::info('RecalculateStrategyCostsCommand finished', [
                'success' => true,
                'ms' => $ms,
                'updated' => $updated,
            ]);

            return ActionResult::success(
                ['updated' => $updated],
                "Recalculated costs for {$updated} strategy rows"
            );
        } catch (\Throwable $e) {
            $ms = (int)((microtime(true) - $startedAt) * 1000);
            Log::warning('RecalculateStrategyCostsCommand failed', [
                'exception' => $e->getMessage(),
                'ms' => $ms,
            ]);
            return ActionResult::failure('Recalculate costs failed: ' . $e->getMessage());
        }
    }
}
