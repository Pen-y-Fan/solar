<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use App\Models\OctopusExport;
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
        //        dd($this->tableFilters);
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No electric export data';

            return [];
        }

        self::$heading = sprintf('Actual electric export and import from %s to %s cost £%0.2f',
            Carbon::parse($rawData->first()['interval_start'], 'London/Europe')
                ->timezone('UTC')
                ->format('D jS M Y H:i'),
            Carbon::parse($rawData->last()['interval_end'], 'London/Europe')
                ->timezone('UTC')
                ->format('jS M H:i'),
            -$rawData->last()['net_accumulative_cost']
        );

        return [
            'datasets' => [
                [
                    'label' => 'Export',
                    'type' => 'bar',
                    'data' => $rawData->map(fn ($item) => -$item['export_consumption']),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Export accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => -$item['export_accumulative_cost']),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'yAxisID' => 'y1',
                ],
                [
                    'label' => 'Import',
                    'data' => $rawData->map(fn ($item) => $item['import_consumption']),
                    'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                    'borderColor' => 'rgb(255, 159, 64)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Import accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => -$item['import_accumulative_cost']),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID' => 'y1',
                ],
                [
                    'label' => 'Net accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => -$item['net_accumulative_cost']),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y1',
                ],
                [
                    'label' => 'Battery (%)',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => $item['battery_percent']),
                    'borderColor' => 'rgb(255, 159, 64)',
                    'yAxisID' => 'y2',
                ],
            ],
            'labels' => $rawData->map(fn ($item) => Carbon::parse($item['interval_start'], 'UTC')
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
        $start = Carbon::parse($date, 'Europe/London')->startOfDay()->timezone('UTC');

        $limit = 48;

        $data = OctopusExport::query()
            ->with(['importCost', 'exportCost', 'octopusImport', 'inverter'])
            ->where(
                'interval_start', '>=',
                $start
            )
            ->where(
                'interval_start',
                '<=',
                $start->copy()->timezone('Europe/London')->endOfDay()->timezone('UTC')
            )
            ->orderBy('interval_start')
            ->limit($limit)
            ->get();

        $exportAccumulativeCost = 0;
        $importAccumulativeCost = 0;

        $result = [];
        foreach ($data as $exportItem) {
            $exportValueIncVat = $exportItem->exportCost?->value_inc_vat ?? 0;
            $importValueIncVat = $exportItem->importCost?->value_inc_vat ?? 0;
            $importConsumption = $exportItem->octopusImport?->consumption ?? 0;
            $battery = $exportItem->inverter?->battery_soc ?? 0;

            $exportCost = $exportValueIncVat * $exportItem->consumption;
            $exportAccumulativeCost += ($exportCost / 100);

            $importCost = -$importValueIncVat * $importConsumption;
            $importAccumulativeCost += ($importCost / 100);

            $result[] = [
                'interval_start' => $exportItem->interval_start,
                'interval_end' => $exportItem->interval_end,
                'updated_at' => $exportItem->updated_at,
                'export_consumption' => $exportItem->consumption,
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
                        'drawOnChartArea' => false, // only want the grid lines for one axis to show up
                    ],
                ],
                'y2' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',

                    // grid line settings
                    'grid' => [
                        'drawOnChartArea' => false, // only want the grid lines for one axis to show up
                    ],
                ],
            ],
        ];
    }
}
