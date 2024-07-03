<?php

namespace App\Filament\Widgets;

use App\Models\AgileExport;
use App\Models\OctopusExport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class OctopusExportChart extends ChartWidget
{
    protected static ?string $heading = 'Electricity export';

    protected static ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No electric export data';
            return [];
        }

        self::$heading = sprintf('Electric export from %s to %s (last updated %s)',
            Carbon::parse($rawData->first()['interval_start'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            Carbon::parse($rawData->last()['interval_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('jS M H:i'),
            Carbon::parse($rawData->last()['updated_at'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M Y H:i')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Export',
                    'type' => 'bar',
                    'data' => $rawData->map(fn($item) => $item['consumption']),
                    'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                    'borderColor' => 'rgb(255, 159, 64)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['accumulative_cost']),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
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

    private function getDatabaseData(): Collection
    {
        $lastExport = OctopusExport::query()
            ->latest('interval_start')
            ->first() ?? now();

        $start = $lastExport->interval_start->timezone('Europe/London')->subDay()
            ->startOfDay()->timezone('UTC');
        $limit = 48;

        $data = OctopusExport::query()
            ->where(
                'interval_start', '>=',
                // possibly use a sub query to get the last interval and sub 1 day
                $start
            )
            ->orderBy('interval_start')
            ->limit($limit)
            ->get();

        $exportCost = AgileExport::query()
            ->where(
                'valid_from',
                '>=',
                $start
            )
            ->orderBy('valid_from')
            ->limit($limit)
            ->get();

        if ($lastExport['interval_start'] <= now()->subDay() && $lastExport['updated_at'] <= now()->subHours(4)) {
            $this->updateOctopusExport();
        }

        $accumulativeCost = 0;
        $result = [];
        foreach ($data as $item) {
            $exportValueIncVat = $exportCost->where('valid_from', '=', $item->interval_start)
                ->first()?->value_inc_vat ?? 0;

            $cost = $exportValueIncVat * $item->consumption;
            $accumulativeCost += ($cost / 100);

            $result[] = [
                'interval_start' => $item->interval_start,
                'interval_end' => $item->interval_end,
                'updated_at' => $item->updated_at,
                'consumption' => $item->consumption,
                'export_value_inc_vat' => $exportValueIncVat,
                'cost' => $cost,
                'accumulative_cost' => $accumulativeCost,
            ];

        }

        return collect($result);
    }

    private function updateOctopusExport(): void
    {
        try {
            (new \App\Actions\OctopusExport)->run();
            Log::info('Successfully updated octopus export data');
        } catch (Throwable $th) {
            Log::error('Error running Octopus export action:', ['error message' => $th->getMessage()]);
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
