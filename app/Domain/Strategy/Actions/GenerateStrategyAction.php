<?php

declare(strict_types=1);

namespace App\Domain\Strategy\Actions;

use App\Domain\Energy\Models\OutgoingOctopus;
use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use App\Domain\Energy\ValueObjects\InverterConsumptionData;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\Helpers\DateUtils;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\Strategy\ValueObjects\CostData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Support\Actions\ActionResult;
use App\Support\Actions\Contracts\ActionInterface;
use Throwable;

class GenerateStrategyAction implements ActionInterface
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
     * @throws Throwable
     */
    /**
     * @deprecated Use execute() returning ActionResult instead.
     */
    public function run(): bool
    {
        return $this->generate();
    }

    public function execute(): ActionResult
    {
        try {
            $ok = $this->generate();
            return $ok
                ? ActionResult::success(null, 'Strategy generated')
                : ActionResult::failure('No data available to generate strategy');
        } catch (Throwable $e) {
            Log::warning('GenerateStrategyAction failed', ['exception' => $e->getMessage()]);
            return ActionResult::failure($e->getMessage());
        }
    }

    private function generate(): bool
    {
        Log::info('Start generation of strategy');

        [$start, $end] = DateUtils::calculateDateRange($this->filter);

        $limit = 48;

        $forecastData = Forecast::query()
            ->with(['importCost', 'exportCost'])
            ->orderBy('period_end')
            ->whereBetween('period_end', [$start, $end])
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

        $weekAgoStart = $start->clone()->timezone('Europe/London')->subWeek()->timezone('UTC');
        $weekAgoEnd = $weekAgoStart->clone()->addDay()->timezone('UTC');
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
            $exportValue = $forecast->period_end->isAfter($eighthJuly2025)
                ? OutgoingOctopus::EXPORT_COST
                : ($forecast->exportCost ? $forecast->exportCost->value_inc_vat : 0);

            $strategies[$forecast->period_end->format('Hi')]['export_value_inc_vat'] = $exportValue;
            $strategies[$forecast->period_end->format('Hi')]['period'] = $forecast->period_end;

            // Create CostData value object for each strategy
            if (isset($strategies[$forecast->period_end->format('Hi')]['import_value_inc_vat'])) {
                $importValue = $strategies[$forecast->period_end->format('Hi')]['import_value_inc_vat'];

                // We'll store the raw values in the database, but we can use the value object
                // to calculate any derived values if needed
                $costData = new CostData(
                    importValueIncVat: $importValue,
                    exportValueIncVat: $exportValue,
                    // We don't have consumption costs at this point
                    consumptionAverageCost: null,
                    consumptionLastWeekCost: null
                );

                // If we need to use any of the CostData methods, we can do so here
                // For example: $netCost = $costData->getNetCost();
            }
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
            $exportValueIncVat = $forecast->exportCost ? $forecast->exportCost->value_inc_vat : 0;

            // Create a CostData value object for this forecast
            $costData = new CostData(
                importValueIncVat: $importValueIncVat,
                exportValueIncVat: $exportValueIncVat,
                consumptionAverageCost: null,
                consumptionLastWeekCost: null
            );

            /* @var InverterConsumptionData|null $consumptionData */
            $consumptionData = $consumptions->first(function ($item) use ($forecast) {
                return $item->time === $forecast->period_end->format('H:i:s');
            });

            $consumption = $consumptionData ? $consumptionData->value : 0;

            $estimatePV = $forecast->pv_estimate / 2;

            $estimatedBatteryRequired = $estimatePV - $consumption;

            $charging = false;

            // Use the CostData value object to get the import value
            if ($this->charge && $costData->importValueIncVat < $this->chargeStrategy) {
                $charging = true;

                $battery += self::BATTERY_MAX_STRATEGY_PER_HALF_HOUR;
            } else {
                $battery += $estimatedBatteryRequired;

                if ($battery < self::BATTERY_MIN) {
                    $battery = self::BATTERY_MIN;
                }
            }

            if ($battery > self::BATTERY_MAX) {
                $battery = self::BATTERY_MAX;
            }

            $result[$forecast->period_end->format('Hi')] = [
                'period'               => $forecast->period_end,
                'import_value_inc_vat' => $costData->importValueIncVat,
                'charging'             => $charging,
                'consumption'          => $consumption,
                'battery_percentage'   => $battery * 25,
            ];
        }

        return collect($result);
    }
}
