<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use App\Filament\Resources\StrategyResource\Pages\ListStrategies;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Collection;

class StrategyChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Manual charge strategy';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No forecast data';

            return [];
        }

        self::$heading = sprintf('Forecast for %s to %s cost £%0.2f',
            $rawData->first()['period_end']
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            $rawData->last()['period_end']
                ->timezone('Europe/London')
                ->format('jS M H:i'),
            $rawData->last()['acc_cost']
        );

        return [
            'datasets' => [
                [
                    'label' => 'Import',
                    'data' => $rawData->map(fn ($item) => $item['import']),
                    'backgroundColor' => 'rgba(255, 205, 86, 0.2)',
                    'borderColor' => 'rgb(255, 205, 86)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Export',
                    'data' => $rawData->map(fn ($item) => -$item['export']),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Acc. Cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => $item['acc_cost']),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y2',
                ],
                [
                    'label' => 'Acc. import cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => $item['import_accumulative_cost']),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID' => 'y2',
                ],
                [
                    'label' => 'Acc. export cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => -$item['export_accumulative_cost']),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'yAxisID' => 'y2',
                ],
                [
                    'label' => 'Battery (%)',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => $item['battery_percent']),
                    'borderColor' => 'rgb(255, 159, 64)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $rawData->map(fn ($item) => sprintf(
                '%s%s',
                $item['charging'] ? '* ' : '',
                $item['period_end']->timezone('Europe/London')->format('H:i'))
            ),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function getDatabaseData(): Collection
    {

        $tableData = $this->getPageTableRecords();

        $accumulativeCost = 0;
        $exportAccumulativeCost = 0;
        $importAccumulativeCost = 0;
        $data = [];

        foreach ($tableData as $strategy) {
            $import = $strategy->import_amount + $strategy->battery_charge_amount;
            $export = $strategy->export_amount;

            $importCost = $import * $strategy->import_value_inc_vat / 100;
            $exportCost = $export * $strategy->export_value_inc_vat / 100;

            $cost = ($importCost - $exportCost);
            $accumulativeCost += $cost;

            $importAccumulativeCost += $importCost;
            $exportAccumulativeCost += $exportCost;

            $data[] = [
                'period_end' => $strategy->period,
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

    protected function getTablePage(): string
    {
        return ListStrategies::class;
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',

                    // grid line settings
                    'grid' => [
                        // only want the grid lines for one axis to show up
                        'drawOnChartArea' => false,
                    ],
                ],
                'y2' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',

                    // grid line settings
                    'grid' => [
                        // only want the grid lines for one axis to show up
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}
