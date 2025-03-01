<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use App\Filament\Resources\StrategyResource\Pages\ListStrategies;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;


class StrategyOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListStrategies::class;
    }

    protected function getStats(): array
    {
        $tableData = $this->getPageTableRecords();
        $total = $tableData->count();
        $totalImport = $tableData->sum('import_amount');
        $importCost = $tableData->reduce(
            fn($carry, $strategy) => $carry + ($strategy->import_amount ?? 0) * ($strategy->import_value_inc_vat ?? 0),
            0);
        $totalBattery = $tableData->sum('battery_charge_amount');
        $batteryCost = $tableData->reduce(
            fn($carry, $strategy) => $carry + ($strategy->battery_charge_amount ?? 0) * ($strategy->import_value_inc_vat ?? 0),
            0);

        return [
            Stat::make('Total strategies', $total),
            Stat::make('Total import', $totalImport),
            Stat::make('Import cost', sprintf('%0.2fp', $importCost)),
            Stat::make('Total battery', $totalBattery),
            Stat::make('Battery cost', sprintf('%0.2fp', $batteryCost)),
            Stat::make('Total cost', sprintf('%0.2fp', $importCost+ $batteryCost)),
        ];
    }
}
