<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use App\Filament\Resources\StrategyResource\Pages\ListStrategies;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

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
            $rawData->sum('cost'),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Acc. grid import',
                    'data' => $rawData->map(fn($item) => $item['consumption']),
                    'backgroundColor' => 'rgba(255, 205, 86, 0.2)',
                    'borderColor' => 'rgb(255, 205, 86)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['cost']),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y2',
                ],
                [
                    'label' => 'Acc. Cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['acc_cost']),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID' => 'y3',
                ],
                [
                    'label' => 'Battery (%)',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['battery_percent']),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $rawData->map(fn($item) => sprintf(
                '%s%s',
                $item['charging'] ? '* ': '',
                $item['period_end']->timezone('Europe/London')->format('H:i'))
            )
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function getDatabaseData()
    {

        $tableData = $this->getPageTableRecords();

        $accumulativeConsumption = 0;
        $accumulativeCost = 0;
        $data = [];

        foreach ($tableData as $strategy) {
            $accumulativeConsumption += $strategy->consumption_manual;
            $cost = (($strategy->import_amount + $strategy->battery_charge_amount) * $strategy->import_value_inc_vat - $strategy->export_amount * $strategy->export_value_inc_vat)/100;
            $accumulativeCost += $cost;

            $data[] = [
                'period_end' => $strategy->period,
                'consumption' => $accumulativeConsumption,
                'cost' => $cost,
                'acc_cost' => $accumulativeCost,
                'charging' => $strategy->strategy_manual,
                'battery_percent' => $strategy->battery_percentage_manual,
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
                    'position' => 'left',

                    // grid line settings
                    'grid' => [
                        // only want the grid lines for one axis to show up
                        'drawOnChartArea' => false,
                    ],
                ],
                'y3' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',

                    // grid line settings
                    'grid' => [
                        // only want the grid lines for one axis to show up
                        'drawOnChartArea' => false,
                    ],
                ]
            ]
        ];
    }
}
