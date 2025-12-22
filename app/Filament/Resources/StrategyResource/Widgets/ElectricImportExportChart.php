<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use Carbon\Exceptions\InvalidFormatException;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;

class ElectricImportExportChart extends ChartWidget
{
    #[Reactive]
    public ?array $tableFilters = null;

    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Electricity export and import';

    protected static ?string $pollingInterval = '120s';

    public ?string $filter = '';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No electric export data';

            return [];
        }

        self::$heading = sprintf(
            'Actual electric export and import from %s to %s cost £%0.2f',
            Carbon::parse($rawData->first()['interval_start'], 'Europe/London')
                ->timezone('UTC')
                ->format('D jS M Y H:i'),
            Carbon::parse($rawData->last()['interval_end'], 'Europe/London')
                ->timezone('UTC')
                ->format('jS M H:i'),
            -$rawData->last()['net_accumulative_cost'] / 100
        );

        return [
            'datasets' => [
                [
                    'label'           => 'Export (p)',
                    'type'            => 'bar',
                    'data'            => $rawData->map(fn($item) => sprintf('%0.2f', $item['export_cost'])),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor'     => 'rgb(54, 162, 235)',
                    'yAxisID'         => 'y',
                ],
                [
                    'label'       => 'Export accumulative cost',
                    'type'        => 'line',
                    'data'        => $rawData->map(fn($item) => sprintf('%0.2f', -$item['export_accumulative_cost'])),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'yAxisID'     => 'y1',
                ],
                [
                    'label'           => 'Import (p)',
                    'data'            => $rawData->map(fn($item) => sprintf('%0.2f', $item['import_cost'])),
                    'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                    'borderColor'     => 'rgb(255, 159, 64)',
                    'yAxisID'         => 'y',
                ],
                [
                    'label'       => 'Import accumulative cost',
                    'type'        => 'line',
                    'data'        => $rawData->map(fn($item) => sprintf('%0.2f', -$item['import_accumulative_cost'])),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID'     => 'y1',
                ],
                [
                    'label'       => 'Net accumulative cost',
                    'type'        => 'line',
                    'data'        => $rawData->map(fn($item) => sprintf('%0.2f', -$item['net_accumulative_cost'])),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID'     => 'y1',
                ],
                [
                    'label'       => 'Battery (%)',
                    'type'        => 'line',
                    'data'        => $rawData->map(fn($item) => $item['battery_percent']),
                    'borderColor' => 'rgb(255, 159, 64)',
                    'yAxisID'     => 'y2',
                ],
            ],
            'labels'   => $rawData->map(fn($item) => Carbon::parse($item['interval_start'], 'UTC')
                ->timezone('Europe/London')
                ->format('H:i')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function getDatabaseData(): Collection
    {
        $date = $this->tableFilters['period']['value'] ?? now('Europe/London')->format('Y-m-d');
        try {
            $start = Carbon::parse($date, 'Europe/London')->subDay()->setTime(16, 0)->timezone('UTC');
        } catch (InvalidFormatException $e) {
            $start = now('Europe/London')->subDay()->setTime(16, 0)->timezone('UTC');
        }

        $limit = 48;

        /** @var \App\Application\Queries\Energy\ElectricImportExportSeriesQuery $query */
        $query = app(\App\Application\Queries\Energy\ElectricImportExportSeriesQuery::class);

        return $query->run($start, $limit);
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
