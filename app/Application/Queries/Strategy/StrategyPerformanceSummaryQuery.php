<?php

declare(strict_types=1);

namespace App\Application\Queries\Strategy;

use App\Domain\Strategy\Models\Strategy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-side query producing KPI summaries for strategies over a date range.
 * Returns a collection with one entry per day containing:
 * - date (Y-m-d)
 * - total_import_kwh
 * - import_cost_pence
 * - total_battery_kwh
 * - battery_cost_pence
 * - export_kwh
 * - export_revenue_pence
 * - self_consumption_kwh
 * - net_cost_pence (import + battery cost - export revenue)
 */
final class StrategyPerformanceSummaryQuery
{
    /**
     * @return Collection<int, array{
     *     date: string,
     *     total_import_kwh: float,
     *     import_cost_pence: float,
     *     total_battery_kwh: float,
     *     battery_cost_pence: float,
     *     export_kwh: float,
     *     export_revenue_pence: float,
     *     self_consumption_kwh: float,
     *     net_cost_pence: float,
     * }>
     */
    public function run(Carbon $startUtc, Carbon $endUtc): Collection
    {
        // inclusive start, inclusive end day
        $start = $startUtc->copy()->startOfDay();
        $end = $endUtc->copy()->endOfDay();

        $enabled = (bool) config('perf.feature_cache_strat_summary', false);
        $ttl = (int) config('perf.strat_summary_ttl', 120);
        if ($enabled) {
            $key = sprintf(
                'strat_summary:%s:%s',
                $start->timezone('UTC')->format('Y-m-d'),
                $end->timezone('UTC')->format('Y-m-d')
            );

            /** @var array<int, array{
            *     date: string,
            *     total_import_kwh: float,
            *     import_cost_pence: float,
            *     total_battery_kwh: float,
            *     battery_cost_pence: float,
            *     export_kwh: float,
            *     export_revenue_pence: float,
            *     self_consumption_kwh: float,
            *     net_cost_pence: float,
            * }> $cached */
            $cached = Cache::remember($key, $ttl, function () use ($start, $end): array {
                return $this->compute($start, $end)->all();
            });

            /** @var Collection<int, array{
            *     date: string,
            *     total_import_kwh: float,
            *     import_cost_pence: float,
            *     total_battery_kwh: float,
            *     battery_cost_pence: float,
            *     export_kwh: float,
            *     export_revenue_pence: float,
            *     self_consumption_kwh: float,
            *     net_cost_pence: float,
            * }> $result */
            $result = collect($cached);

            return $result;
        }

        return $this->compute($start, $end);
    }

    /**
     * @return Collection<int, array{
     *     date: string,
     *     total_import_kwh: float,
     *     import_cost_pence: float,
     *     total_battery_kwh: float,
     *     battery_cost_pence: float,
     *     export_kwh: float,
     *     export_revenue_pence: float,
     *     self_consumption_kwh: float,
     *     net_cost_pence: float,
     * }>
     */
    private function compute(Carbon $start, Carbon $end): Collection
    {
        /** @var Collection<int, Strategy> $rows */
        $rows = Strategy::query()
            ->select([
                'period',
                'import_amount',
                'battery_charge_amount',
                'export_amount',
                'import_value_inc_vat',
                'export_value_inc_vat',
            ])
            ->whereBetween('period', [$start, $end])
            ->orderBy('period')
            ->get();

        return $rows
            ->groupBy(fn (Strategy $s) => $s->period->timezone('UTC')->format('Y-m-d'))
            ->map(function (Collection $dayRows, string $date): array {
                $totalImport = (float) $dayRows->sum('import_amount');
                $importCost = (float) $dayRows->reduce(
                    fn ($c, $s) => $c + ((float) ($s->import_amount ?? 0)) * ((float) ($s->import_value_inc_vat ?? 0)),
                    0.0
                );
                $totalBattery = (float) $dayRows->sum('battery_charge_amount');
                $batteryCost = (float) $dayRows->reduce(
                    fn ($c, $s) => $c
                        + ((float) ($s->battery_charge_amount ?? 0))
                        * ((float) ($s->import_value_inc_vat ?? 0)),
                    0.0
                );
                $exportKwh = (float) $dayRows->sum('export_amount');
                $exportRevenue = (float) $dayRows->reduce(
                    fn ($c, $s) => $c + ((float) ($s->export_amount ?? 0)) * ((float) ($s->export_value_inc_vat ?? 0)),
                    0.0
                );
                $selfConsumption = max(0.0, $totalImport - $exportKwh);
                $netCost = $importCost + $batteryCost - $exportRevenue;

                return [
                    'date' => $date,
                    'total_import_kwh' => $totalImport,
                    'import_cost_pence' => $importCost,
                    'total_battery_kwh' => $totalBattery,
                    'battery_cost_pence' => $batteryCost,
                    'export_kwh' => $exportKwh,
                    'export_revenue_pence' => $exportRevenue,
                    'self_consumption_kwh' => $selfConsumption,
                    'net_cost_pence' => $netCost,
                ];
            })
            ->values();
    }
}
