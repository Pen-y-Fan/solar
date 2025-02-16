<?php

namespace App\Filament\Widgets;

use App\Actions\OctopusImport as OctopusImportAction;
use App\Models\AgileImport;
use App\Models\Forecast;
use App\Models\Inverter;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ForecastChart extends ChartWidget
{
    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Forecast';

    public ?string $filter = 'today';

    protected static ?string $pollingInterval = '120s';

    private const BATTERY_MIN = 0.4;

    private const BATTERY_MAX = 4.0;

    protected function getFilters(): ?array
    {
        return [
            'yesterday' => 'Yesterday',
            'yesterday10' => 'Yesterday (10%)',
            'yesterday90' => 'Yesterday (90%)',
            'today' => 'Today',
            'today10' => 'Today (10%)',
            'today90' => 'Today (90%)',
            'tomorrow' => 'Tomorrow',
            'tomorrow10' => 'Tomorrow (10%)',
            'tomorrow90' => 'Tomorrow (90%)',
        ];
    }

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No forecast data';
            return [];
        }

        self::$heading = sprintf('Forecast for %s to %s cost £%0.2f (without charging battery)',
            $rawData->first()['period_end']
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            $rawData->last()['period_end']
                ->timezone('Europe/London')
                ->format('jS M H:i'),
            $rawData->sum('cost')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Usage',
                    'data' => $rawData->map(fn($item) => -$item['consumption']),
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
                    'label' => 'Cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['acc_cost']),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y3',
                ],
                [
                    'label' => 'Battery (%)',
                    'type' => 'line',
                    'data' => $rawData->map(fn($item) => $item['battery_percent']),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $rawData->map(fn($item) => $item['period_end']
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
        $startDate = match ($this->filter) {
            'yesterday', 'yesterday10', 'yesterday90' => now('Europe/London')->subDay()->startOfDay()->timezone('UTC'),
            'today', 'today10', 'today90' => now('Europe/London')->startOfDay()->timezone('UTC'),
            'tomorrow', 'tomorrow10', 'tomorrow90' => now('Europe/London')->addDay()->startOfDay()->timezone('UTC'),
        };

        $limit = 48;

        $forecastData = Forecast::query()
            ->with('importCost')
            ->orderBy('period_end')
            ->whereBetween('period_end', [$startDate, $startDate->copy()->timezone('Europe/London')->endOfDay()->timezone('UTC')])
            ->limit($limit)
            ->orderBy('period_end')
            ->get();

        if ($forecastData->count() === 0) {
            return collect();
        }

        $averageConsumptions = Inverter::query()
            ->select(DB::raw('time(period) as `time`, avg(`consumption`) as `value`'))
            ->where(
                'period',
                '>',
                now()->timezone('Europe/London')->subdays(21)
                    ->startOfDay()
                    ->timezone('UTC')
            )
            ->groupBy('time')
            ->get();

        // start at 10% battery
        $battery = self::BATTERY_MIN;
        $accumulativeValue = 0;
        $accumulativeCost = 0;
        $result = [];

        foreach ($forecastData as $forecast) {
            $importValueIncVat = $forecast->importCost?->value_inc_vat ?? 0;

            $averageConsumption = $averageConsumptions->where('time', '=', $forecast->period_end->format('H:i:s'))
                ->first() ?? 0;

            if ($averageConsumption === 0) {
                return collect([]);
            }
            /*
             * The battery start off at self::BATTERY_MIN
             * if battery + pv_estimate - avg. consumption < self::BATTERY_MIN then usage is the difference
             *   (battery - self::BATTERY_MIN) & battery = self::BATTERY_MIN
             * if battery + pv_estimate - avg. consumption > self::BATTERY_MIN then export is the difference
             *   (self::BATTERY_MIN - battery) & battery = self::BATTERY_MIN
             * otherwise usage and export = 0, as the battery was used.
             */

            $estimate = match ($this->filter) {
                'yesterday', 'today', 'tomorrow' => $forecast->pv_estimate / 2,
                'yesterday10', 'today10', 'tomorrow10' => $forecast->pv_estimate10 / 2,
                'yesterday90', 'today90', 'tomorrow90' => $forecast->pv_estimate90 / 2,
            };

            $battery += $estimate - $averageConsumption->value;

            $usage = 0;
            $export = 0;

            if ($battery < self::BATTERY_MIN) {
                $usage = self::BATTERY_MIN - $battery;
                $battery = self::BATTERY_MIN;
            }

            if ($battery > self::BATTERY_MAX) {
                $export = $battery - self::BATTERY_MAX;
                $battery = self::BATTERY_MAX;
            }

            $accumulativeValue += $export - $usage;
            $accumulativeCost -= $importValueIncVat * ($export - $usage) / 100;
            $cost = $usage * $importValueIncVat / 100;

            $result[] = [
                'period_end' => $forecast->period_end,
                'updated_at' => $forecast->updated_at,
                'pv_estimate' => $forecast->pv_estimate,
                'consumption' => $accumulativeValue,
                'import_value_inc_vat' => $importValueIncVat,
                'cost' => $cost,
                'acc_cost' => $accumulativeCost,
                'battery_percent' => $battery * 0.25,
            ];

        }
//Log::info('ForecastChart',[$result]);
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
