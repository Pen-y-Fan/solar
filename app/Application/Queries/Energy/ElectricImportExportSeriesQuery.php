<?php

declare(strict_types=1);

namespace App\Application\Queries\Energy;

use App\Domain\Energy\Models\OctopusExport;
use Illuminate\Support\Collection;

/**
 * Provides import/export consumption and accumulative cost series for a given day
 * (or range start) with battery overlay.
 *
 * Output collection of arrays containing:
 *  - interval_start (string UTC datetime)
 *  - interval_end (string UTC datetime)
 *  - updated_at (string datetime)
 *  - export_consumption (float kWh)
 *  - import_consumption (float kWh)
 *  - export_value_inc_vat (float pence)
 *  - import_value_inc_vat (float pence)
 *  - export_cost (float £)
 *  - import_cost (float £)
 *  - export_accumulative_cost (float £)
 *  - import_accumulative_cost (float £)
 *  - net_accumulative_cost (float £)
 *  - battery_percent (float)
 */
final class ElectricImportExportSeriesQuery
{
    /**
     * @return Collection<int, array{
     *     interval_start: string,
     *     interval_end: string,
     *     updated_at: string,
     *     export_consumption: float,
     *     import_consumption: float,
     *     export_value_inc_vat: float,
     *     import_value_inc_vat: float,
     *     export_cost: float,
     *     import_cost: float,
     *     export_accumulative_cost: float,
     *     import_accumulative_cost: float,
     *     net_accumulative_cost: float,
     *     battery_percent: float,
     * }>
     */
    public function run(\DateTimeInterface $startUtc, int $limit = 48): Collection
    {
        $data = OctopusExport::query()
            ->with(['importCost', 'strategy', 'octopusImport', 'inverter'])
            ->where('interval_start', '>=', $startUtc)
            ->orderBy('interval_start')
            ->limit($limit)
            ->get();

        $exportAccumulativeCost = 0.0; // £
        $importAccumulativeCost = 0.0; // £

        $result = [];
        foreach ($data as $exportItem) {
            $exportValueIncVat = (float) (
                $exportItem->strategy
                    ? ($exportItem->strategy->export_value_inc_vat ?? 0)
                    : 0
            );
            $importValueIncVat = (float) (
                $exportItem->importCost
                    ? ($exportItem->importCost->value_inc_vat ?? 0)
                    : 0
            );
            $importConsumption = (float) (
                $exportItem->octopusImport
                    ? ($exportItem->octopusImport->consumption ?? 0)
                    : 0
            );
            $battery = (float)($exportItem->inverter ? ($exportItem->inverter->battery_soc ?? 0) : 0);

            // Convert pence * kWh to £
            $exportCost = ($exportValueIncVat * (float)($exportItem->consumption ?? 0)) / 100.0; // £
            $exportAccumulativeCost += $exportCost;

            $importCost = (-(float) $importValueIncVat * $importConsumption) / 100.0; // £
            // negative spend becomes negative value, but chart negates again
            $importAccumulativeCost += $importCost;

            $result[] = [
                'interval_start' => (string)$exportItem->interval_start,
                'interval_end' => (string)$exportItem->interval_end,
                'updated_at' => (string)$exportItem->updated_at,
                'export_consumption' => (float)($exportItem->consumption ?? 0),
                'import_consumption' => $importConsumption,
                'export_value_inc_vat' => $exportValueIncVat,
                'import_value_inc_vat' => $importValueIncVat,
                'export_cost' => $exportCost,
                'import_cost' => $importCost,
                'export_accumulative_cost' => $exportAccumulativeCost,
                'import_accumulative_cost' => $importAccumulativeCost,
                'net_accumulative_cost' => $exportAccumulativeCost + $importAccumulativeCost,
                'battery_percent' => $battery,
            ];
        }

        return collect($result);
    }
}
