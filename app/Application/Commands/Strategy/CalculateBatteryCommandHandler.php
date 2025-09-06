<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Strategy\Models\Strategy;
use App\Helpers\CalculateBatteryPercentage;
use App\Support\Actions\ActionResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CalculateBatteryCommandHandler implements CommandHandler
{
    public function __construct(private readonly CalculateBatteryPercentage $calculator)
    {
    }

    public function handle(Command $command): ActionResult
    {
        \assert($command instanceof CalculateBatteryCommand);

        $startedAt = microtime(true);
        Log::info('CalculateBatteryCommand started');

        try {
            // Determine date boundaries in Europe/London, then convert to UTC for DB
            $dateStr = $command->date ?: Carbon::now('Europe/London')->format('Y-m-d');
            $start = Carbon::parse($dateStr, 'Europe/London')->startOfDay()->timezone('UTC');
            $end = Carbon::parse($dateStr, 'Europe/London')->endOfDay()->timezone('UTC');

            $count = 0;

            if ($command->simulate) {
                // For simulate, just iterate but do not persist
                $strategies = Strategy::query()->whereBetween('period', [$start, $end])->get();
                foreach ($strategies as $strategy) {
                    $this->calculateForStrategy($strategy, persist: false);
                    $count++;
                }

                $ms = (int) ((microtime(true) - $startedAt) * 1000);
                Log::info('CalculateBatteryCommand finished', [
                    'success' => true,
                    'ms' => $ms,
                    'count' => $count,
                    'simulate' => true,
                ]);
                return ActionResult::success(null, "Simulated battery calculation for {$count} strategy rows");
            }

            // Default current battery for calculation; could be enhanced later to use strategy state
            $currentBattery = 10;
            $this->calculator->startBatteryPercentage($currentBattery);

            DB::transaction(function () use (&$count, $start, $end) {
                $strategies = Strategy::query()->whereBetween('period', [$start, $end])->get();
                foreach ($strategies as $strategy) {
                    $this->calculateForStrategy($strategy, persist: true);
                    $count++;
                }
            });

            $ms = (int) ((microtime(true) - $startedAt) * 1000);
            Log::info('CalculateBatteryCommand finished', [
                'success' => true,
                'ms' => $ms,
                'count' => $count,
                'simulate' => false,
            ]);

            return ActionResult::success(null, "Calculated battery for {$count} strategy rows");
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $startedAt) * 1000);
            Log::warning('CalculateBatteryCommand failed', [
                'exception' => $e->getMessage(),
                'ms' => $ms,
            ]);

            return ActionResult::failure('Battery calculation failed: ' . $e->getMessage());
        }
    }

    private function calculateForStrategy(Strategy $strategy, bool $persist): void
    {
        $consumption = $strategy->getConsumptionDataValueObject()->manual ?? 0.0;
        $pvEstimate = (float) (optional($strategy->forecast)->pv_estimate ?? 0.0);
        $charging = (bool) ($strategy->strategy_manual ?? false);

        [$batteryPercentage, $chargeAmount, $importAmount, $exportAmount] = $this->calculator
            ->isCharging($charging)
            ->consumption($consumption)
            ->estimatePVkWh($pvEstimate)
            ->calculate();

        if ($persist) {
            $strategy->battery_percentage1 = $batteryPercentage;
            $strategy->battery_charge_amount = $chargeAmount;
            $strategy->battery_percentage_manual = $batteryPercentage;
            $strategy->import_amount = $importAmount;
            $strategy->export_amount = $exportAmount;
            $strategy->save();
        }
    }
}
