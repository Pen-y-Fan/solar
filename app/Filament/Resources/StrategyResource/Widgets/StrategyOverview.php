<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use App\Application\Queries\Strategy\StrategyPerformanceSummaryQuery;
use App\Filament\Resources\StrategyResource\Pages\ListStrategies;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StrategyOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListStrategies::class;
    }

    protected function getStats(): array
    {
        $date = $this->tableFilters['period']['value'] ?? now('Europe/London')->format('Y-m-d');

        try {
            $selectedLondon = Carbon::parse($date, 'Europe/London');
        } catch (\Throwable) {
            $selectedLondon = Carbon::now('Europe/London');
        }

        $startLondon = $selectedLondon->copy()->startOfDay();
        $endLondon = $selectedLondon->copy()->endOfDay();

        $startUtc = $startLondon->copy()->timezone('UTC');
        $endUtc = $endLondon->copy()->timezone('UTC');

        /** @var StrategyPerformanceSummaryQuery $query */
        $query = app(StrategyPerformanceSummaryQuery::class);
        $summary = $query->run($startUtc, $endUtc);

        $totals = [
            'import' => (float) $summary->sum('total_import_kwh'),
            'import_cost' => (float) $summary->sum('import_cost_pence'),
            'battery' => (float) $summary->sum('total_battery_kwh'),
            'battery_cost' => (float) $summary->sum('battery_cost_pence'),
            'export' => (float) $summary->sum('export_kwh'),
            'export_revenue' => (float) $summary->sum('export_revenue_pence'),
            'self_consumption' => (float) $summary->sum('self_consumption_kwh'),
            'net_cost' => (float) $summary->sum('net_cost_pence'),
        ];

        return [
            Stat::make('Total import', number_format($totals['import'], 2) . ' kWh'),
            Stat::make('Import cost', sprintf('%0.2fp', $totals['import_cost'])),
            Stat::make('Total battery', number_format($totals['battery'], 2) . ' kWh'),
            Stat::make('Battery cost', sprintf('%0.2fp', $totals['battery_cost'])),
            Stat::make('Exported', number_format($totals['export'], 2) . ' kWh'),
            Stat::make('Export revenue', sprintf('%0.2fp', $totals['export_revenue'])),
            Stat::make('Self-consumption', number_format($totals['self_consumption'], 2) . ' kWh'),
            Stat::make('Net cost', sprintf('%0.2fp', $totals['net_cost'])),
        ];
    }
}
