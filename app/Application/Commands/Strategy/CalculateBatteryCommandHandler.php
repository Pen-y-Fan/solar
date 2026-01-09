<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Contracts\CommandHandler;
use App\Domain\Strategy\Helpers\DateUtils;
use App\Domain\Strategy\Models\Strategy;
use App\Helpers\CalculateBatteryPercentage;
use App\Support\Actions\ActionResult;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class CalculateBatteryCommandHandler implements CommandHandler
{
    public function __construct(private CalculateBatteryPercentage $calculator)
    {
    }

    public function handle(Command $command): ActionResult
    {
        assert($command instanceof CalculateBatteryCommand);

        $startedAt = microtime(true);
        Log::info('CalculateBatteryCommand started');

        try {
            [$start, $end] = DateUtils::calculateDateRange1600to1600($command->date);
            $prevPeriod = (clone $start)->subMinutes(30);
            $prevBattery = Strategy::where('period', $prevPeriod)
                ->first('battery_percentage_manual')->battery_percentage_manual ?? 100;
            $this->calculator->startBatteryPercentage($prevBattery);

            $strategies = Strategy::query()
                ->with('forecast:id,pv_estimate,period_end')
                ->whereBetween('period', [$start, $end])
                ->orderBy('period')
                ->get([
                    'id',
                    'period',
                    'strategy_manual',
                    'consumption_last_week',
                    'consumption_average',
                    'consumption_manual',
                ]);
            $count = 0;

            foreach ($strategies as $strategy) {
                $this->calculateForStrategy($strategy, !$command->simulate);
                $count++;
            }

            if ($command->simulate) {
                CalculateBatteryCommandHandler::logExecutionTime($startedAt, $count, $command);

                return ActionResult::success(
                    null,
                    sprintf("Simulated battery calculation for %s strategy rows", $count)
                );
            }

            $updates = $strategies->map(function (Strategy $strategy): array {
                return [
                    'id'                        => $strategy->id,
                    'battery_percentage1'       => $strategy->battery_percentage1,
                    'battery_charge_amount'     => $strategy->battery_charge_amount,
                    'battery_percentage_manual' => $strategy->battery_percentage_manual,
                    'import_amount'             => $strategy->import_amount,
                    'export_amount'             => $strategy->export_amount,
                ];
            })->all();

            Strategy::upsert(
                $updates,
                ['id'],
                [
                    'battery_percentage1',
                    'battery_charge_amount',
                    'battery_percentage_manual',
                    'import_amount',
                    'export_amount',
                ]
            );


            CalculateBatteryCommandHandler::logExecutionTime($startedAt, $count, $command);

            return ActionResult::success(null, sprintf("Calculated battery for %s strategy rows", $count));
        } catch (Throwable $e) {
            $ms = (int)((microtime(true) - $startedAt) * 1000);
            Log::warning('CalculateBatteryCommand failed', [
                'exception' => $e->getMessage(),
                'ms'        => $ms,
            ]);

            return ActionResult::failure('Battery calculation failed: ' . $e->getMessage());
        }
    }

    private function calculateForStrategy(Strategy $strategy, bool $persist): void
    {
        $consumption = $strategy->getConsumptionDataValueObject()->manual ?? 0.0;
        $pvEstimate = (float)(optional($strategy->forecast)->pv_estimate ?? 0.0);
        $charging = $strategy->strategy_manual ?? false;

        $result = $this->calculator
            ->isCharging($charging)
            ->consumption($consumption)
            ->estimatePVkWh($pvEstimate)
            ->calculate();

        if ($persist) {
            $strategy->battery_percentage1 = $result->batteryPercentage;
            $strategy->battery_charge_amount = $result->chargeAmount;
            $strategy->battery_percentage_manual = $result->batteryPercentage;
            $strategy->import_amount = $result->importAmount;
            $strategy->export_amount = $result->exportAmount;
        }
    }

    protected static function logExecutionTime($startedAt, int $count, CalculateBatteryCommand $command): void
    {
        $ms = (int)((microtime(true) - $startedAt) * 1000);
        Log::info('CalculateBatteryCommand finished', [
            'success'  => true,
            'ms'       => $ms,
            'count'    => $count,
            'simulate' => $command->simulate,
        ]);
    }
}
