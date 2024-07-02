<?php

namespace App\Filament\Widgets;

use App\Actions\OctopusImport as OctopusImportAction;
use App\Models\AgileImport;
use App\Models\OctopusImport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class OctopusImportChart extends ChartWidget
{
    protected static ?string $heading = 'Electricity import';
    protected static ?string $pollingInterval = '20s';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No electric usage data';
            return [];
        }

        $label = sprintf('usage from %s to %s',
            Carbon::parse($rawData->first()['interval_start'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M H:i'),
            Carbon::parse($rawData->last()['interval_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M Y H:i')
        );

        self::$heading = 'Electric ' . $label;

        $label = str($label)->ucfirst();

        return [
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $rawData->map(fn($item) => -$item['consumption']),
                    'backgroundColor' => 'rgba(255, 205, 86, 0.2)',
                    'borderColor' => 'rgb(255, 205, 86)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['accumulative_cost']),
                    'borderColor' => 'rgb(255, 99, 132)',
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

    private function getDatabaseData()
    {
        $lastImport = OctopusImport::query()
            ->latest('interval_start')
            ->first() ?? now();
        $limit = 48;
        $start = $lastImport->interval_start->timezone('Europe/London')->subDay()
            ->startOfDay()->timezone('UTC');

        $importData = OctopusImport::query()
            ->where(
                'interval_start', '>=',
                // possibly use a sub query to get the last interval and sub 1 day
                $start
            )
            ->orderBy('interval_start')
            ->limit($limit)
            ->get();

        $importCost = AgileImport::query()
            ->where(
                'valid_from',
                '>=',
                $start
            )
            ->orderBy('valid_from')
            ->limit($limit)
            ->get();


        if ($lastImport->interval_start <= now()->subDay() && $lastImport->updated_at <= now()->subHours(4)) {
            $this->updateOctopusImport();
        }

        $accumulativeCost = 0;
        $result = [];
        foreach ($importData as $item) {
            $importValueIncVat = $importCost->where('valid_from', '=', $item->interval_start)
                ->first()?->value_inc_vat ?? 0;

            $cost = -$importValueIncVat * $item->consumption;
            $accumulativeCost += ($cost / 100);

            $result[] = [
                'interval_start' => $item->interval_start,
                'interval_end' => $item->interval_end,
                'updated_at' => $item->updated_at,
                'consumption' => $item->consumption,
                'import_value_inc_vat' => $importValueIncVat,
                'cost' => $cost,
                'accumulative_cost' => $accumulativeCost,
            ];

        }

        return collect($result);
    }

    private function updateOctopusImport(): void
    {
        try {
            (new OctopusImportAction)->run();
            Log::info('Successfully updated octopus import cost data');
        } catch (Throwable $th) {
            Log::error('Error running Octopus import cost action:', ['error message' => $th->getMessage()]);
        }

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
}
