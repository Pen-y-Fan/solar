<?php

declare(strict_types=1);

namespace App\Application\Queries\Strategy;

use App\Domain\Strategy\Models\Strategy;
use Illuminate\Support\Collection;

/**
 * Builds the read-model series used by the StrategyChart widget.
 *
 * Input: Collection<Strategy> (e.g., page table records)
 * Output: Collection<array{
 *   period_end: \Carbon\CarbonImmutable,
 *   import: float,
 *   export: float,
 *   cost: float,
 *   acc_cost: float,
 *   charging: bool|int|string|null,
 *   battery_percent: float|int|null,
 *   import_accumulative_cost: float,
 *   export_accumulative_cost: float,
 * }>
 */
final class StrategyManualSeriesQuery
{
    /**
     * @param Collection<int, Strategy> $strategies
     * @return Collection
     */
    public function run(Collection $strategies): Collection
    {
        $accumulativeCost = 0.0;
        $exportAccumulativeCost = 0.0;
        $importAccumulativeCost = 0.0;

        /** @var array<int, array{period_end: \Carbon\CarbonImmutable, import: float, export: float, cost: float, acc_cost: float, charging: bool|int|string|null, battery_percent: float|int|null, import_accumulative_cost: float, export_accumulative_cost: float}> $data */
        $data = [];

        /** @var Strategy $strategy */
        foreach ($strategies as $strategy) {
            // Maintain existing widget math but typed
            $import = (float) (($strategy->import_amount ?? 0) + ($strategy->battery_charge_amount ?? 0));
            $export = (float) ($strategy->export_amount ?? 0);

            $importCost = $import * (float) ($strategy->import_value_inc_vat ?? 0) / 100.0;
            $exportCost = $export * (float) ($strategy->export_value_inc_vat ?? 0) / 100.0;

            $cost = ($importCost - $exportCost);
            $accumulativeCost += $cost;

            $importAccumulativeCost += $importCost;
            $exportAccumulativeCost += $exportCost;

            /** @var \Carbon\CarbonImmutable|null $period */
            $period = $strategy->period instanceof \Carbon\CarbonInterface
                ? $strategy->period->toImmutable()
                : null;

            $data[] = [
                'period_end' => $period ?? now()->toImmutable(),
                'import' => $import,
                'export' => $export,
                'cost' => $cost,
                'acc_cost' => $accumulativeCost,
                'charging' => $strategy->strategy_manual,
                'battery_percent' => $strategy->battery_percentage_manual,
                'import_accumulative_cost' => $importAccumulativeCost,
                'export_accumulative_cost' => $exportAccumulativeCost,
            ];
        }

        return collect($data);
    }
}
