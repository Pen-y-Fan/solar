<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use App\Application\Queries\Strategy\StrategyManualSeriesQuery;
use App\Domain\Strategy\Models\Strategy;
use App\Filament\Resources\StrategyResource\Pages\ListStrategies;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Collection;

class StrategyChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Strategy';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No strategy data';

            return [];
        }

        self::$heading = sprintf(
            'Strategy for %s to %s cost £%0.2f',
            $rawData->first()['period_end']
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            $rawData->last()['period_end']
                ->timezone('Europe/London')
                ->format('jS M H:i'),
            $rawData->last()['acc_cost'] / 100
        );

        return [
            'datasets' => [
                [
                    'label'           => 'Import (p)',
                    'data'            => $rawData->map(fn($item) => sprintf('%0.2f', $item['import_cost'])),
                    'backgroundColor' => 'rgba(255, 205, 86, 0.2)',
                    'borderColor'     => 'rgb(255, 205, 86)',
                    'yAxisID'         => 'y',
                ],
                [
                    'label'           => 'Export (p)',
                    'data'            => $rawData->map(fn($item) => sprintf('%0.2f', -$item['export_cost'])),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor'     => 'rgb(54, 162, 235)',
                    'yAxisID'         => 'y',
                ],
                [
                    'label'       => 'Acc. Cost',
                    'type'        => 'line',
                    'data'        => $rawData->map(fn($item) => $item['acc_cost']),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID'     => 'y2',
                ],
                [
                    'label'       => 'Acc. import cost',
                    'type'        => 'line',
                    'data'        => $rawData->map(fn($item) => $item['import_accumulative_cost']),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID'     => 'y2',
                ],
                [
                    'label'       => 'Acc. export cost',
                    'type'        => 'line',
                    'data'        => $rawData->map(fn($item) => -$item['export_accumulative_cost']),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'yAxisID'     => 'y2',
                ],
                [
                    'label'       => 'Battery (%)',
                    'type'        => 'line',
                    'data'        => $rawData->map(fn($item) => $item['battery_percent']),
                    'borderColor' => 'rgb(255, 159, 64)',
                    'yAxisID'     => 'y1',
                ],
            ],
            'labels'   => $rawData->map(fn($item) => sprintf(
                '%s%s',
                $item['charging'] ? '* ' : '',
                $item['period_end']->timezone('Europe/London')->format('H:i')
            )),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function getDatabaseData(): Collection
    {
        /** @var \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Eloquent\Collection<int, Strategy> $tableData */
        $tableData = $this->getPageTableRecords();

        // Ensure we pass a Collection into the query, similar to CostChart
        if ($tableData instanceof \Illuminate\Contracts\Pagination\Paginator) {
            /** @var Collection<int, Strategy> $strategyCollection */
            $strategyCollection = collect($tableData->items());
        } else {
            /** @var Collection<int, Strategy> $strategyCollection */
            $strategyCollection = $tableData;
        }

        /** @var StrategyManualSeriesQuery $query */
        $query = app(StrategyManualSeriesQuery::class);
        return $query->run($strategyCollection);
    }

    protected function getTablePage(): string
    {
        return ListStrategies::class;
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y'  => [
                    'type'     => 'linear',
                    'display'  => true,
                    'position' => 'left',
                ],
                'y1' => [
                    'type'     => 'linear',
                    'display'  => true,
                    'position' => 'right',

                    'grid' => [

                        'drawOnChartArea' => false,
                    ],
                ],
                'y2' => [
                    'type'     => 'linear',
                    'display'  => true,
                    'position' => 'right',

                    'grid' => [

                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}
