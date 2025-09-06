<?php

declare(strict_types=1);

namespace App\Application\Queries\Strategy;

use App\Domain\Strategy\Models\Strategy;
use Illuminate\Support\Carbon;

final class StrategyDailySummaryQuery
{
    /**
     * Produce a simple daily summary for Strategies occurring on the given local day (Europe/London).
     *
     * Returns an associative array with totals and averages useful for dashboards.
     *
     * @return array{
     *   date: string,
     *   count: int,
     *   total_import_kwh: float,
     *   total_export_kwh: float,
     *   avg_import_value_inc_vat: float,
     *   avg_export_value_inc_vat: float,
     *   net_cost_estimate: float
     * }
     */
    public function run(\DateTimeInterface $day): array
    {
        // Interpret the provided day in Europe/London (as in StrategyResource filters), then convert to UTC for DB.
        $start = Carbon::parse($day->format('Y-m-d'), 'Europe/London')->startOfDay()->timezone('UTC');
        $end = Carbon::parse($day->format('Y-m-d'), 'Europe/London')->endOfDay()->timezone('UTC');

        $query = Strategy::query()->whereBetween('period', [$start, $end]);

        $count = (int) $query->count();

        // Fetch only needed columns for aggregation to keep things light.
        $rows = Strategy::query()
            ->whereBetween('period', [$start, $end])
            ->get([
                'import_amount',
                'export_amount',
                'import_value_inc_vat',
                'export_value_inc_vat',
            ]);

        $totalImportKwh = (float) ($rows->sum('import_amount') ?? 0.0);
        $totalExportKwh = (float) ($rows->sum('export_amount') ?? 0.0);
        $avgImportValue = $rows->avg('import_value_inc_vat');
        $avgExportValue = $rows->avg('export_value_inc_vat');
        $avgImportValue = $avgImportValue !== null ? (float) $avgImportValue : 0.0;
        $avgExportValue = $avgExportValue !== null ? (float) $avgExportValue : 0.0;

        $netCostEstimate = ($totalImportKwh * $avgImportValue) - ($totalExportKwh * $avgExportValue);

        return [
            'date' => $start->timezone('Europe/London')->format('Y-m-d'),
            'count' => $count,
            'total_import_kwh' => round($totalImportKwh, 4),
            'total_export_kwh' => round($totalExportKwh, 4),
            'avg_import_value_inc_vat' => round($avgImportValue, 4),
            'avg_export_value_inc_vat' => round($avgExportValue, 4),
            'net_cost_estimate' => round($netCostEstimate, 4),
        ];
    }
}
