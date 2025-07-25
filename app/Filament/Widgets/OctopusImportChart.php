<?php

namespace App\Filament\Widgets;

use App\Domain\Energy\Actions\OctopusImport as OctopusImportAction;
use App\Domain\Energy\Models\OctopusImport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class OctopusImportChart extends ChartWidget
{
    private const UPDATE_FREQUENCY_HOURS = 4;

    protected static ?string $heading = 'Electricity import';

    protected static ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No electric usage data';

            return [];
        }

        self::$heading = sprintf(
            'Electric import from %s to %s (£%.2f)',
            $rawData->first()['interval_start']
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            $rawData->last()['interval_end']
                ->timezone('Europe/London')
                ->format('jS M H:i'),
            -$rawData->last()['accumulative_cost']
        );

        return [
            'datasets' => [
                [
                    'label' => 'Usage',
                    'data' => $rawData->map(fn ($item) => $item['consumption']),
                    'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                    'borderColor' => 'rgb(255, 159, 64)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Accumulative cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => $item['accumulative_cost']),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID' => 'y1',
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
        $lastImport = $this->getLatestImport();

        if (
            is_null($lastImport)
            || (
                $lastImport->interval_start <= now()->subDay()
                && $lastImport->updated_at <= now()->subHours(self::UPDATE_FREQUENCY_HOURS)
            )
        ) {
            $this->updateOctopusImport();
            $lastImport = $this->getLatestImport();
        }

        $limit = 48;
        $start = ($lastImport ? $lastImport->interval_start : now())->timezone('Europe/London')->subDay()
            ->startOfDay()->timezone('UTC');

        $importData = OctopusImport::query()
            ->with('importCost')
            ->where(
                'interval_start',
                '>=',
                $start
            )
            ->orderBy('interval_start')
            ->limit($limit)
            ->get();

        $accumulativeCost = 0;
        $result = [];
        foreach ($importData as $item) {
            $importValueIncVat = $item->importCost ? $item->importCost->value_inc_vat : 0;

            $cost = $importValueIncVat * $item->consumption;
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
            (new OctopusImportAction())->run();
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

                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }

    private function getLatestImport(): OctopusImport|null
    {
        return OctopusImport::query()
            ->latest('interval_start')
            ->first();
    }
}
