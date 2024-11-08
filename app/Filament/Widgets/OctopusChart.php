<?php

namespace App\Filament\Widgets;

use App\Models\AgileExport;
use App\Models\AgileImport;
use App\Models\OctopusExport;
use App\Models\OctopusImport;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OctopusChart extends ChartWidget
{
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

        self::$heading = sprintf('Electric export and import from %s to %s',
            Carbon::parse($rawData->first()['interval_start'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            Carbon::parse($rawData->last()['interval_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('jS M H:i'),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Export',
                    'type' => 'bar',
                    'data' => $rawData->map(fn($item) => $item['export_consumption']),
                    // 54, 162, 235, 0.2
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Export accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['export_accumulative_cost']),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'yAxisID' => 'y1',
                ],
                [
                    'label' => 'Import',
                    'data' => $rawData->map(fn($item) => -$item['import_consumption']),
                    'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                    'borderColor' => 'rgb(255, 159, 64)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Import accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['import_accumulative_cost']),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID' => 'y1',
                ],
                [
                    'label' => 'Net accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['net_accumulative_cost']),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $rawData->map(fn($item) => Carbon::parse($item['interval_start'], 'UTC')
                ->timezone('Europe/London')
                ->format('H:i')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        $periods = CarbonPeriod::create('now -1 day', '-1 day', 35);

        $data = [];
        foreach ($periods as $period) {
            $data[$period->format('Y-m-d')] = $period->format('D jS M');
        }

        return $data;
    }

    private function getDatabaseData(): Collection
    {
        if ($this->filter === '') {
            $this->filter = now()->startOfDay()->subDay()->format('Y-m-d');
        }
        $start = Carbon::parse($this->filter, 'Europe/London')->startOfDay()->timezone('UTC');

        $limit = 48;

        $data = OctopusExport::query()
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

        $exportCosts = AgileExport::query()
            ->where(
                'valid_from',
                '>=',
                $start
            )
            ->orderBy('valid_from')
            ->limit($limit)
            ->get();

        $importData = OctopusImport::query()
            ->where(
                'interval_start', '>=',
                $start
            )
            ->orderBy('interval_start')
            ->limit($limit)
            ->get();

        $importCosts = AgileImport::query()
            ->where(
                'valid_from',
                '>=',
                $start
            )
            ->orderBy('valid_from')
            ->limit($limit)
            ->get();


        $exportAccumulativeCost = 0;
        $importAccumulativeCost = 0;

        $result = [];
        foreach ($data as $item) {
            $exportValueIncVat = $exportCosts->where('valid_from', '=', $item->interval_start)
                ->first()?->value_inc_vat ?? 0;

            $importConsumption = $importData->where('interval_start', '=', $item->interval_start)
                ->first()?->consumption ?? 0;

            $importValueIncVat = $importCosts->where('valid_from', '=', $item->interval_start)
                ->first()?->value_inc_vat ?? 0;

            $exportCost = $exportValueIncVat * $item->consumption;
            $exportAccumulativeCost += ($exportCost / 100);

            $importCost = -$importValueIncVat * $importConsumption;
            $importAccumulativeCost += ($importCost / 100);

            $result[] = [
                'interval_start' => $item->interval_start,
                'interval_end' => $item->interval_end,
                'updated_at' => $item->updated_at,
                'export_consumption' => $item->consumption,
                'import_consumption' => $importConsumption,
                'export_value_inc_vat' => $exportValueIncVat,
                'import_value_inc_vat' => $importValueIncVat,
                'export_cost' => $exportCost,
                'import_cost' => $importCost,
                'export_accumulative_cost' => $exportAccumulativeCost,
                'import_accumulative_cost' => $importAccumulativeCost,
                'net_accumulative_cost' => $exportAccumulativeCost+$importAccumulativeCost,
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
                ]
            ]
        ];
    }

    private function getLastExport()
    {
        return OctopusExport::query()
            ->latest('interval_start')
            ->first();
    }

    private function getLatestImport()
    {
        return OctopusImport::query()
            ->latest('interval_start')
            ->first();
    }
}