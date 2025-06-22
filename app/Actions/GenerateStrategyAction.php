<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Forecast;
use App\Models\Inverter;
use App\Models\Strategy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateStrategyAction
{
    private const BATTERY_MIN = 0.4;

    private const BATTERY_MAX = 4.0;

    private const BATTERY_MAX_STRATEGY_PER_HALF_HOUR = 1.0;

    private float $chargeStrategy = 0.0;

    private bool $charge = true;

    public ?string $filter = 'today';

    /**
     * @throws \Throwable
     */
    public function run(): bool
    {
        Log::info('Start generation of strategy');

        $startDate = Carbon::parse($this->filter, 'Europe/London')
            ->timezone('Europe/London')
            ->startOfDay()
            ->timezone('UTC');

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
            return false;
        }

        $averageConsumptions = Inverter::query()
            ->select(DB::raw('time(period) as `time`, avg(`consumption`) as `value`'))
            ->where(
                'period',
                '>',
                now()->timezone('Europe/London')
                    ->subdays(21)
                    ->startOfDay()
                    ->timezone('UTC')
            )
            ->groupBy('time')
            ->get();

        if ($averageConsumptions->count() === 0) {
            return false;
        }

        $weekAgpConsumptions = Inverter::query()
            ->whereBetween('period', [
                $startDate->clone()->timezone('Europe/London')->subWeek()->timezone('UTC'),
                $startDate->clone()->timezone('Europe/London')->subWeek()->endOfDay()->timezone('UTC'),
            ])
            ->get();

        $weekAgpConsumptions->each(function ($consumption) {
            $consumption->time = $consumption->period->format('H:i:s');
            $consumption->value = $consumption->consumption;
        });

        $importCosts = [];
        $minCost = 0;
        $averageCost = 0;

        $forecastData->each(function ($forecast) use (&$importCosts) {
            $importCost = $forecast->importCost?->value_inc_vat ?? 0;

            if ($importCost > 0) {
                $importCosts[] = $importCost;
            }
        });

        $importCosts = [];

        $forecastData->each(function ($forecast) use (&$importCosts) {
            $importCost = $forecast->importCost?->value_inc_vat ?? 0;

            if ($importCost > 0) {
                $importCosts[] = $importCost;
            }
        });

        if (count($importCosts) > 0) {
            $averageCost = array_sum($importCosts) / count($importCosts);

            $minCost = min($importCosts);
        }

        // make two calls to generate strategy, one passing in the average, the other passing in weekAgo.
        // Upsert the results to Strategies.
        // Call a method to calculate the strategy, passing in the consumption ($averageConsumptions)
        // Save the results to an empty array
        // Call a method to calculate the strategy, passing in the consumption ($weekAgpConsumptions)
        // update the array
        // save/upsert the data

        $this->chargeStrategy = ($averageCost + $minCost + $minCost) / 3;
        $firstPassStrategy1 = $this->getConsumption($forecastData, $averageConsumptions);
        $secondPassStrategy1 = $this->getConsumption($forecastData, $weekAgpConsumptions);
        $this->chargeStrategy = count($importCosts) > 0 ? ($averageCost + $minCost + $minCost + $minCost) / 4 : 0;
        $thirdPassStrategy2 = $this->getConsumption($forecastData, $averageConsumptions);

        $strategies = [];
        $firstPassStrategy1->each(function ($item, $key) use (&$strategies) {
            $strategies[$key]['import_value_inc_vat'] = $item['import_value_inc_vat'];
            $strategies[$key]['strategy1'] = $item['charging'];
            $strategies[$key]['consumption_average'] = $item['consumption'];
        });

        $secondPassStrategy1->each(function ($item, $key) use (&$strategies) {
            $strategies[$key]['consumption_last_week'] = $item['consumption'];
        });

        $thirdPassStrategy2->each(function ($item, $key) use (&$strategies) {
            $strategies[$key]['strategy2'] = $item['charging'];

        });

        $forecastData->each(function ($forecast) use (&$strategies) {
            $strategies[$forecast->period_end->format('Hi')]['export_value_inc_vat'] = $forecast->exportCost?->value_inc_vat ?? 0;
            $strategies[$forecast->period_end->format('Hi')]['period'] = $forecast->period_end;
        });

        Strategy::upsert(
            $strategies,
            uniqueBy: ['period'],
            update: [
                'strategy1',
                'strategy2',
                'consumption_last_week',
                'consumption_average',
                'import_value_inc_vat',
                'export_value_inc_vat',
            ]
        );

        return true;
    }

    public function getConsumption(
        \Illuminate\Database\Eloquent\Collection|array $forecastData,
        \Illuminate\Database\Eloquent\Collection|array $consumptions,
    ): \Illuminate\Support\Collection {
        $battery = self::BATTERY_MIN;
        $result = [];

        foreach ($forecastData as $forecast) {
            $importValueIncVat = $forecast->importCost?->value_inc_vat ?? 0;

            Log::info('Charge at: '.$forecast->period_end->format('H:i:s').' import cost '.$importValueIncVat.' battery '.$battery);
            $consumption = $consumptions->where(
                'time',
                $forecast->period_end->format('H:i:s')
            )->first()?->value ?? 0;

            $estimatePV = $forecast->pv_estimate / 2;

            $estimatedBatteryRequired = $estimatePV - $consumption;

            $charging = false;

            if ($this->charge && $importValueIncVat < $this->chargeStrategy) {
                $charging = true;

                $battery += self::BATTERY_MAX_STRATEGY_PER_HALF_HOUR;
                if ($battery > self::BATTERY_MAX) {
                    // reset the battery to max
                    $battery = self::BATTERY_MAX;
                }
            } else {
                // We are not charging so use the battery then sort out the import or export
                $battery += $estimatedBatteryRequired;

                if ($battery < self::BATTERY_MIN) {
                    $battery = self::BATTERY_MIN;
                }

                if ($battery > self::BATTERY_MAX) {
                    $battery = self::BATTERY_MAX;
                }
            }

            $result[$forecast->period_end->format('Hi')] = [
                'period' => $forecast->period_end,
                'import_value_inc_vat' => $importValueIncVat,
                'charging' => $charging,
                'consumption' => $consumption,
                'battery_percentage' => $battery * 25,
            ];
        }

        return collect($result);
    }
}
