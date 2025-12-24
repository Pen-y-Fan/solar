<?php

namespace App\Filament\Widgets;

use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Energy\Models\Inverter;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ForecastChart extends ChartWidget
{
    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Solcast forecast';

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
            self::$heading = 'No solcast forecast data';

            return [];
        }

        if ($this->charge) {
            $strategy = sprintf('charge strategy: %0.2f or less', $this->chargeStrategy);
        } else {
            $strategy = '(without charging battery)';
        }

        self::$heading = sprintf(
            'Solcast forecast for %s to %s cost £%0.2f %s',
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
        $enabled = (bool) config('perf.feature_cache_forecast_chart', false);
        $ttl = (int) config('perf.forecast_chart_ttl', 60);
        if ($enabled) {
            $key = sprintf(
                'forecast_chart:%s:%s',
                $this->filter,
                now('Europe/London')->startOfDay()->format('Y-m-d')
            );

            return Cache::remember($key, $ttl, function () {
                return $this->getDatabaseDataUncached();
            });
        }

        return $this->getDatabaseDataUncached();
    }

    private function getDatabaseDataUncached(): Collection
    {
        $startDate = match ($this->filter) {
            'yesterday', 'yesterday10', 'yesterday90', 'yesterday_strategy1', 'yesterday_strategy2' =>
            now('Europe/London')
                ->subDay()
                ->startOfDay()
                ->timezone('UTC'),
            'tomorrow', 'tomorrow10', 'tomorrow90', 'tomorrow_strategy1', 'tomorrow_strategy2' => now('Europe/London')
                ->addDay()
                ->startOfDay()
                ->timezone('UTC'),
            default => now('Europe/London')
                ->startOfDay()
                ->timezone('UTC'),
        };

        $this->charge = str_contains($this->filter, '_strategy');

        $limit = 48;

        $forecastData = Forecast::query()
            ->select(['id', 'period_end', 'pv_estimate', 'pv_estimate10', 'pv_estimate90', 'updated_at'])
            ->with([
                'importCost:id,valid_from,value_inc_vat',
                'exportCost:id,valid_from,value_inc_vat',
            ])
            ->whereBetween('period_end', [
                $startDate,
                $startDate->copy()->timezone('Europe/London')->endOfDay()->timezone('UTC'),
            ])
            ->orderBy('period_end')
            ->limit($limit)
            ->get();

        if ($forecastData->count() === 0) {
            return collect();
        }

        // Using DB::raw creates dynamic properties that PHPStan can't detect,
        // we're selecting avg(consumption) as 'value'
        $averageConsumptions = Inverter::query()
            ->select(DB::raw('time(period) as `time`, avg(`consumption`) as `value`'))
            ->where(
                'period',
                '>',
                now()->timezone('Europe/London')->subDays(21)
                    ->startOfDay()
                    ->timezone('UTC')
            )
            ->groupBy('time')
            ->get();

        if ($averageConsumptions->count() === 0) {
            return collect();
        }

        $battery = self::BATTERY_MIN;
        $accumulativeConsumption = 0;
        $accumulativeCost = 0;
        $result = [];

        if ($this->charge) {
            $importCosts = [];

            $forecastData->each(function ($forecast) use (&$importCosts) {
                $importCost = $forecast->importCost ? $forecast->importCost->value_inc_vat : null;

                if ($importCost !== null) {
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
            $importValueIncVat = $forecast->importCost ? $forecast->importCost->value_inc_vat : 0;
            $exportValueIncVat = $forecast->exportCost ? $forecast->exportCost->value_inc_vat : 0;

            $averageConsumptionRecord = $averageConsumptions->where(
                'time',
                $forecast->period_end->format('H:i:s')
            )->first();

            // Using array access for the dynamic property created by DB::raw
            $averageConsumption = $averageConsumptionRecord ? (float)($averageConsumptionRecord['value'] ?? 0) : 0;

            $estimatePV = match ($this->filter) {
                'yesterday10', 'today10', 'tomorrow10', => $forecast->pv_estimate10 / 2,
                'yesterday90', 'today90', 'tomorrow90' => $forecast->pv_estimate90 / 2,
                 default => $forecast->pv_estimate / 2,
            };

            $estimatedBatteryRequired = $estimatePV - $averageConsumption;

            $import = 0;
            $export = 0;

            if ($this->charge && $importValueIncVat < $this->chargeStrategy) {
                Log::info('Charge at: ' . $forecast->period_end->format('H:i:s (UTC)'));

                $maxChargeAmount = min(self::BATTERY_MAX_STRATEGY_PER_HALF_HOUR, self::BATTERY_MAX - $battery);

                $battery += self::BATTERY_MAX_STRATEGY_PER_HALF_HOUR;

                if ($battery > self::BATTERY_MAX) {
                    if ($estimatedBatteryRequired > 0 && $estimatedBatteryRequired > $maxChargeAmount) {
                        $export = $estimatedBatteryRequired - $maxChargeAmount;
                    } else {
                        $import = $maxChargeAmount - $estimatedBatteryRequired;
                    }

                    $battery = self::BATTERY_MAX;
                } else {
                    $import = $maxChargeAmount - $estimatedBatteryRequired;
                }
            } else {
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

        $collection = collect($result);

        // Optional downsampling for long ranges (Proposal B)
        if (config('perf.forecast_downsample', false)) {
            $bucket = max(1, (int) config('perf.forecast_bucket_minutes', 30));
            $collection = $this->downsampleForecast($collection, $bucket);
        }

        return $collection;
    }

    /**
     * Downsample forecast points by selecting the last point per bucket to preserve
     * cumulative series shapes while reducing point count.
     *
     * @param Collection $collection
     * @param int $bucketMinutes
     * @return Collection
     */
    private function downsampleForecast(Collection $collection, int $bucketMinutes): Collection
    {
        return $collection
            ->groupBy(function (array $row) use ($bucketMinutes) {
                $t = $row['period_end']->copy()->timezone('UTC');
                $minute = (int) $t->format('i');
                $floored = $minute - ($minute % $bucketMinutes);
                return $t->copy()->minute($floored)->second(0)->format('Y-m-d H:i:s');
            })
            ->map(fn ($group) => $group->last())
            ->values();
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
                'y2' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',

                    'grid' => [

                        'drawOnChartArea' => false,
                    ],
                ],
                'y3' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',

                    'grid' => [

                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}
