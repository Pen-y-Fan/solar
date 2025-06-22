<?php

namespace App\Filament\Widgets;

use App\Models\Forecast;
use App\Models\Inverter;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ForecastChart extends ChartWidget
{
    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Forecast';

    public ?string $filter = 'today';

    protected static ?string $pollingInterval = '120s';

    private const BATTERY_MIN = 0.4;

    private const BATTERY_MAX = 4.0;

    private const BATTERY_MAX_STRATEGY_PER_HALF_HOUR = 1.0;

    private float $chargeStrategy = 0.0;

    private bool $charge = false;

    protected function getFilters(): ?array
    {
        return [
            'yesterday' => 'Yesterday',
            'yesterday_strategy1' => 'Yesterday (Strategy 1)',
            'yesterday_strategy2' => 'Yesterday (Strategy 2)',
            'yesterday10' => 'Yesterday (10%)',
            'yesterday90' => 'Yesterday (90%)',
            'today' => 'Today',
            'today_strategy1' => 'Today (Strategy 1)',
            'today_strategy2' => 'Today (Strategy 2)',
            'today10' => 'Today (10%)',
            'today90' => 'Today (90%)',
            'tomorrow' => 'Tomorrow',
            'tomorrow_strategy1' => 'Tomorrow (Strategy 1)',
            'tomorrow_strategy2' => 'Tomorrow (Strategy 2)',
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

        if ($this->charge) {
            $strategy = sprintf('charge strategy: %0.2f or less', $this->chargeStrategy);
        } else {
            $strategy = '(without charging battery)';
        }

        self::$heading = sprintf(
            'Forecast for %s to %s cost £%0.2f %s',
            $rawData->first()['period_end']
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            $rawData->last()['period_end']
                ->timezone('Europe/London')
                ->format('jS M H:i'),
            $rawData->sum('cost'),
            $strategy
        );

        return [
            'datasets' => [
                [
                    'label' => 'Acc. grid import',
                    'data' => $rawData->map(fn ($item) => -$item['consumption']),
                    'backgroundColor' => 'rgba(255, 205, 86, 0.2)',
                    'borderColor' => 'rgb(255, 205, 86)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => $item['cost']),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'yAxisID' => 'y2',
                ],
                [
                    'label' => 'Acc. Cost',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => $item['acc_cost']),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'yAxisID' => 'y3',
                ],
                [
                    'label' => 'Battery (%)',
                    'type' => 'line',
                    'data' => $rawData->map(fn ($item) => $item['battery_percent']),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $rawData->map(fn ($item) => sprintf(
                '%s%s',
                $item['import_value_inc_vat'] < $this->chargeStrategy ? '* ' : '',
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
        $startDate = match ($this->filter) {
            'yesterday', 'yesterday10', 'yesterday90', 'yesterday_strategy1', 'yesterday_strategy2' =>
            now('Europe/London')
                ->subDay()
                ->startOfDay()
                ->timezone('UTC'),
            'today', 'today10', 'today90', 'today_strategy1', 'today_strategy2' => now('Europe/London')
                ->startOfDay()
                ->timezone('UTC'),
            'tomorrow', 'tomorrow10', 'tomorrow90', 'tomorrow_strategy1', 'tomorrow_strategy2' => now('Europe/London')
                ->addDay()
                ->startOfDay()
                ->timezone('UTC'),
        };

        $this->charge = str_contains($this->filter, '_strategy');

        $limit = 48;

        $forecastData = Forecast::query()
            ->with(['importCost', 'exportCost'])
            ->orderBy('period_end')
            ->whereBetween('period_end', [
                $startDate,
                $startDate->copy()->timezone('Europe/London')->endOfDay()->timezone('UTC'),
            ])
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

        if ($averageConsumptions->count() === 0) {
            return collect([]);
        }

        // start at 10% battery (0.4 kWh)
        $battery = self::BATTERY_MIN;
        $accumulativeConsumption = 0;
        $accumulativeCost = 0;
        $result = [];

        if ($this->charge) {
            $importCosts = [];

            $forecastData->each(function ($forecast) use (&$accumulativeConsumption, &$importCosts) {
                $importCost = $forecast->importCost?->value_inc_vat ?? 0;

                if ($importCost > 0) {
                    $importCosts[] = $importCost;
                }
            });

            if (count($importCosts) > 0) {
                $averageCost = array_sum($importCosts) / count($importCosts);

                $minCost = min($importCosts);

                if (str_contains($this->filter, '_strategy1')) {
                    $this->chargeStrategy = ($averageCost + $minCost + $minCost) / 3;
                } else {
                    $this->chargeStrategy = ($averageCost + $minCost + $minCost + $minCost) / 4;
                }
            }
        }

        foreach ($forecastData as $forecast) {
            $importValueIncVat = $forecast->importCost?->value_inc_vat ?? 0;
            $exportValueIncVat = $forecast->exportCost?->value_inc_vat ?? 0;

            $averageConsumption = $averageConsumptions->where(
                'time',
                $forecast->period_end->format('H:i:s')
            )->first() ?? 0;

            $estimatePV = match ($this->filter) {
                'yesterday', 'today', 'tomorrow', 'yesterday_strategy1', 'today_strategy1', 'tomorrow_strategy1',
                'yesterday_strategy2', 'today_strategy2', 'tomorrow_strategy2' => $forecast->pv_estimate / 2,
                'yesterday10', 'today10', 'tomorrow10', => $forecast->pv_estimate10 / 2,
                'yesterday90', 'today90', 'tomorrow90' => $forecast->pv_estimate90 / 2,
            };

            $estimatedBatteryRequired = $estimatePV - $averageConsumption->value;

            $import = 0;
            $export = 0;

            if ($this->charge && $importValueIncVat < $this->chargeStrategy) {
                // Let's charge using cheap rate electricity
                Log::info('Charge at: ' . $forecast->period_end->format('H:i:s (UTC)'));

                // we are charging so negative $estimatedBatteryRequired means we charge the battery
                // the import cost is the chargeAmount + $estimatedBatteryRequired
                // if the battery reaches MAX the export will cut in chargeAmount + PV generated over the max
                $maxChargeAmount = min(self::BATTERY_MAX_STRATEGY_PER_HALF_HOUR, self::BATTERY_MAX - $battery);

                $battery += self::BATTERY_MAX_STRATEGY_PER_HALF_HOUR;

                // We need to charge the battery from grid
                // The PV may be supplying some charge
                // if the battery is over the battery max and PV is more than demand ($estimatedBatteryRequired > 0)
                // we may be exporting, but only if the $estimatedBatteryRequired > $maxChargeAmount, otherwise we are
                // importing the difference
                if ($battery > self::BATTERY_MAX) {
                    if ($estimatedBatteryRequired > 0 && $estimatedBatteryRequired > $maxChargeAmount) {
                        // battery has reached max, we are exporting excess PV
                        // e.g. battery was 4.3 we charged to 4.4 and PC is 0.5, consumption is 0.3
                        // the charge amount is 0.7 we used 0.2 from excess PV
                        $export = $estimatedBatteryRequired - $maxChargeAmount;
                    } else {
                        // battery has just reached max, but we are not exporting, we can take excess PV off the import
                        $import = $maxChargeAmount - $estimatedBatteryRequired;
                    }

                    // reset the battery to max
                    $battery = self::BATTERY_MAX;
                } else {
                    // The battery is charging, so we are importing the charge amount +/- the required
                    $import = $maxChargeAmount - $estimatedBatteryRequired;
                }
            } else {
                // We are not charging so use the battery then sort out the import or export
                $battery += $estimatedBatteryRequired;

                if ($battery < self::BATTERY_MIN) {
                    $import = self::BATTERY_MIN - $battery;
                    $battery = self::BATTERY_MIN;
                }

                if ($battery > self::BATTERY_MAX) {
                    $export = $battery - self::BATTERY_MAX;
                    $battery = self::BATTERY_MAX;
                }
            }

            $accumulativeConsumption += $export - $import;
            $accumulativeCost += ($importValueIncVat * $import / 100) - ($exportValueIncVat * $export / 100);
            $importCost = $import * $importValueIncVat / 100;

            $result[] = [
                'period_end' => $forecast->period_end,
                'updated_at' => $forecast->updated_at,
                'pv_estimate' => $forecast->pv_estimate,
                'consumption' => $accumulativeConsumption,
                'import_value_inc_vat' => $importValueIncVat,
                'cost' => $importCost,
                'acc_cost' => $accumulativeCost,
                'battery_percent' => $battery * 25,
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
                ],
            ],
        ];
    }
}
