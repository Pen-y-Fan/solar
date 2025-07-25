<?php

declare(strict_types=1);

namespace App\Domain\Strategy\Actions;

use App\Domain\Energy\Models\OutgoingOctopus;
use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateStrategyAction
{
    private const BATTERY_MIN = 0.4;

    private const BATTERY_MAX = 4.0;

    private const BATTERY_MAX_STRATEGY_PER_HALF_HOUR = 1.0;

    private float $chargeStrategy = 0.0;

    private bool $charge = true;

    public ?string $filter = 'today';

    public function __construct(
        private readonly InverterRepositoryInterface $inverterRepository
    ) {
    }

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

        $averageConsumptions = $this->inverterRepository->getAverageConsumptionByTime(now());

        if ($averageConsumptions->count() === 0) {
            return false;
        }

        $weekAgoStart = $startDate->clone()->timezone('Europe/London')->subWeek()->timezone('UTC');
        $weekAgoEnd = $startDate->clone()->timezone('Europe/London')->subWeek()->endOfDay()->timezone('UTC');
        $weekAgpConsumptions = $this->inverterRepository->getConsumptionForDateRange($weekAgoStart, $weekAgoEnd);

        $importCosts = [];
        $minCost = 0;
        $averageCost = 0;

        $forecastData->each(function ($forecast) use (&$importCosts) {
            $importCost = $forecast->importCost ? $forecast->importCost->value_inc_vat : 0;

            if ($importCost > 0) {
                $importCosts[] = $importCost;
            }
        });

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
        }

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

        $eighthJuly2025 = Carbon::createFromFormat('Y-m-d', '2025-07-08', 'UTC');

        $forecastData->each(function ($forecast) use (&$strategies, $eighthJuly2025) {
            $strategies[$forecast->period_end->format('Hi')]['export_value_inc_vat'] =
                $forecast->period_end->isAfter($eighthJuly2025)
                    ? OutgoingOctopus::EXPORT_COST
                    : ($forecast->exportCost ? $forecast->exportCost->value_inc_vat : 0);
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
        Collection|array $forecastData,
        \Illuminate\Support\Collection $consumptions,
    ): \Illuminate\Support\Collection {
        $battery = self::BATTERY_MIN;
        $result = [];

        foreach ($forecastData as $forecast) {
            $importValueIncVat = $forecast->importCost ? $forecast->importCost->value_inc_vat : 0;

            Log::info('Charge at: ' . $forecast->period_end->format('H:i:s') . ' import cost '
                . $importValueIncVat . ' battery ' . $battery);

            $consumptionData = $consumptions->first(function ($item) use ($forecast) {
                return $item->time === $forecast->period_end->format('H:i:s');
            });

            $consumption = $consumptionData ? $consumptionData->value : 0;

            $estimatePV = $forecast->pv_estimate / 2;

            $estimatedBatteryRequired = $estimatePV - $consumption;

            $charging = false;

            if ($this->charge && $importValueIncVat < $this->chargeStrategy) {
                $charging = true;

                $battery += self::BATTERY_MAX_STRATEGY_PER_HALF_HOUR;
                if ($battery > self::BATTERY_MAX) {
                    $battery = self::BATTERY_MAX;
                }
            } else {
                $battery += $estimatedBatteryRequired;

                if ($battery < self::BATTERY_MIN) {
                    $battery = self::BATTERY_MIN;
                }

                if ($battery > self::BATTERY_MAX) {
                    $battery = self::BATTERY_MAX;
                }
            }

            $result[$forecast->period_end->format('Hi')] = [
                'period'               => $forecast->period_end,
                'import_value_inc_vat' => $importValueIncVat,
                'charging'             => $charging,
                'consumption'          => $consumption,
                'battery_percentage'   => $battery * 25,
            ];
        }

        return collect($result);
    }
}
