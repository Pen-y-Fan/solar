<?php

declare(strict_types=1);

namespace App\Domain\Strategy\Actions;

use App\Application\Commands\Strategy\DTOs\BatteryCalculationResult;
use App\Domain\Energy\Models\OutgoingOctopus;
use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\DTOs\StrategyCostCalculatorRequest;
use App\Domain\Strategy\Enums\StrategyType;
use App\Domain\Strategy\Helpers\DateUtils;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\Strategy\Services\StrategyCostCalculator;
use App\Support\Actions\ActionResult;
use App\Support\Actions\Contracts\ActionInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateStrategyAction implements ActionInterface
{
    public ?string $filter = 'today';
    public array $errors = [];
    /**
     * @var Collection<Strategy>|null
     */
    public ?Collection $baseStrategy = null;
    public ?Collection $forecastData = null;
    public ?float $baselineCost = null;
    public ?int $baselineEndBattery = null;
    public bool $baselineValid = false;
    /**
     * @var Collection<BatteryCalculationResult>|null
     */
    public ?Collection $baselineBatteryResults = null;

    public ?float $optimizedCost = null;
    public ?float $optimizedEndBattery = null;
    public bool $optimizedValid = false;
    /**
     * @var Collection<BatteryCalculationResult>|null
     */
    public ?Collection $optimizedBatteryResults = null;
    private int $startBatteryPercentage = 100;
    private float $baseStrategyThreshold = 0.0;

    public function __construct(
        private readonly InverterRepositoryInterface $inverterRepository
    ) {
    }

    public function execute(): ActionResult
    {
        try {
            $ok = $this->generate();
            return $ok
                ? ActionResult::success(
                    null,
                    sprintf(
                        '%d strategies generated. Strategy 1 will cost £%.2f. Strategy 2 will cost £%.2f',
                        count($this->baseStrategy ?? []),
                        $this->baselineCost / 100,
                        $this->optimizedCost / 100
                    )
                )
                : ActionResult::failure($this->errors[0] ?? 'There was an error generating strategies');
        } catch (Throwable $e) {
            Log::warning('GenerateStrategyAction failed', ['exception' => $e->getMessage()]);
            $this->errors[] = Str::limit($e->getMessage());
            return ActionResult::failure($this->errors[0] ?? 'There was an failure generating strategies');
        }
    }

    private function generate(): bool
    {
        Log::info('Start generation of strategy');

        if (!$this->findOrCreateStrategyData()) {
            Log::warning('findOrCreateStrategyData failed', ['errors' => $this->errors]);
            return false;
        }

        Log::info('Start calculation of baseline costs');
        $this->calculateBaselineCosts();
        if (!$this->baselineValid) {
            Log::warning('Baseline strategy invalid', ['errors' => $this->errors]);
            return false;
        }

        Log::info('Start calculation of optimized strategy costs');
        $this->calculateStrategyCosts();
        if (!$this->optimizedValid) {
            Log::warning('Optimized strategy invalid', ['errors' => $this->errors]);
            return false;
        }

        Log::info('Start calculating final manual strategy');
        $this->calculateFinalCost();

        Log::info('Start upserting strategy data');
        $this->upsertStrategy();

        Log::info('Strategy generation completed');
        return true;
    }

    private function findOrCreateStrategyData(): bool
    {
        $this->errors = [];

        [$start, $end] = DateUtils::calculateDateRange1600to1600($this->filter);
        $limit = 48;

        $prevPeriod = $start->copy()->subMinutes(30);
        $this->startBatteryPercentage = Strategy::where('period', $prevPeriod)
            ->first('battery_percentage_manual')->battery_percentage_manual ?? 100;

        $strategyCount = Strategy::query()
            ->whereBetween('period', [$start, $end])
            ->count();

        if ($strategyCount >= 15) {
            // Strategy already exists, skip generation
            $this->getStrategy($start, $end, $limit);
            return true;
        }

        $forecastData = Forecast::query()
            ->with([
                'importCost:id,value_inc_vat,valid_from',
                'exportCost:id,value_inc_vat,valid_from',
            ])
            ->whereBetween('period_end', [$start, $end])
            ->limit($limit)
            ->orderBy('period_end')
            ->get([
                'id',
                'period_end',
                'pv_estimate',
            ]);

        if ($forecastData->count() === 0) {
            $this->errors[] = 'No forecast data available';
            return false;
        }

        $this->forecastData = $forecastData;
        $averageConsumptions = $this->inverterRepository->getAverageConsumptionByTime($start);

        if ($averageConsumptions->count() === 0) {
            $this->errors[] = 'No average consumption data available';
            return false;
        }

        $weekAgoStart = $start->clone()->timezone('Europe/London')->subWeek()->timezone('UTC');
        $weekAgoEnd = $weekAgoStart->clone()->addDay()->timezone('UTC');
        $weekAgoConsumptions = $this->inverterRepository->getConsumptionForDateRange($weekAgoStart, $weekAgoEnd);

        // Create base data
        $strategies = [];
        $eighthJuly2025 = Carbon::createFromFormat('Y-m-d', '2025-07-08', 'UTC');

        foreach ($forecastData as $forecast) {
            $dateTimePeriod = $forecast->period_end->format('YmdHi');
            $importValue = optional($forecast->importCost)->value_inc_vat ?? 0.0;
            $exportValue = $forecast->period_end->isAfter($eighthJuly2025)
                ? OutgoingOctopus::EXPORT_COST
                : optional($forecast->exportCost)->value_inc_vat ?? 0.0;

            $avgConsumptionData = $averageConsumptions->first(function ($item) use ($forecast) {
                return $item->time === $forecast->period_end->format('H:i:s');
            });
            $avgConsumption = $avgConsumptionData->value ?? 0.0;

            $weekAgoConsumptionData = $weekAgoConsumptions->first(function ($item) use ($forecast) {
                return $item->time === $forecast->period_end->format('H:i:s');
            });
            $weekAgoConsumption = $weekAgoConsumptionData->value ?? 0.0;

            $strategies[$dateTimePeriod] = [
                'period'                => $forecast->period_end,
                'import_value_inc_vat'  => $importValue,
                'export_value_inc_vat'  => $exportValue,
                'consumption_average'   => $avgConsumption,
                'consumption_last_week' => $weekAgoConsumption,
                'consumption_manual'    => $weekAgoConsumption,
            ];
        }

        Strategy::upsert(
            collect($strategies)->values()->toArray(),
            uniqueBy: ['period'],
            update: [
                'import_value_inc_vat',
                'export_value_inc_vat',
                'consumption_average',
                'consumption_last_week',
                'consumption_manual',
            ]
        );

        $this->getStrategy($start, $end, $limit);

        return true;
    }

    private function calculateBaselineCosts(): void
    {
        $calculator = new StrategyCostCalculator();

        $result = $this->calculateBaseStrategyThreshold();
        if (!$result) {
            $this->baselineValid = false;
            return;
        }
        foreach ($this->baseStrategy as $strategy) {
            $strategy->strategy2 = $strategy->import_value_inc_vat < $this->baseStrategyThreshold;
        }

        $i = 1;
        do {
            $request = new StrategyCostCalculatorRequest(
                $this->baseStrategy,
                $this->startBatteryPercentage,
                StrategyType::Strategy2
            );
            $calcResult = $calculator->calculateTotalCost($request);
            $endBat = $calcResult->endBattery ?? 0;
            $this->baselineValid = $endBat >= 90;
            if ($this->baselineValid) {
                $this->baselineCost = $calcResult->totalCost ?? 0.0;
                $this->baselineEndBattery = $calcResult->endBattery ?? 0.0;
                $this->baselineBatteryResults = $calcResult->batteryResults ?? [];
                return;
            }
            $periodToCharge = count($this->baseStrategy) - $i;
            if ($periodToCharge >= 0) {
                $this->baseStrategy[$periodToCharge]->strategy2 = true;
            }

            $i++;
        } while ($i < 6);

        $this->errors[] = sprintf(
            'Baseline end battery %.1f%% < 90%%',
            $this->baselineEndBattery
        );
    }

    private function calculateStrategyCosts(): void
    {
        $nightCandidates = [];
        $dayCandidates = [];

        foreach ($this->baseStrategy as $index => $strategy) {
            $hour = $strategy->period->hour;
            $isNight = $hour >= 16 || $hour < 8;
            $candidate = [
                'index' => $index,
                'price' => $strategy->import_value_inc_vat ?? 999,
            ];
            if ($isNight) {
                $nightCandidates[] = $candidate;
            } else {
                $dayCandidates[] = $candidate;
            }

            $strategy->strategy1 = false;
        }

        usort($nightCandidates, fn($a, $b) => $a['price'] <=> $b['price']);
        usort($dayCandidates, fn($a, $b) => $a['price'] <=> $b['price']);
        $cheapNightIndices = array_slice(array_column($nightCandidates, 'index'), 0, 3);
        $cheapDayIndices = array_slice(array_column($dayCandidates, 'index'), 0, 3);
        $cheapIndices = array_merge($cheapNightIndices, $cheapDayIndices);

        foreach ($cheapIndices as $cheapIndex) {
            $this->baseStrategy[$cheapIndex]->strategy1 = true;
        }

        $i = 1;
        do {
            $calculator = new StrategyCostCalculator();

            $request = new StrategyCostCalculatorRequest(
                $this->baseStrategy,
                $this->startBatteryPercentage,
                StrategyType::Strategy1
            );
            $calcResult = $calculator->calculateTotalCost($request);
            $this->optimizedCost = $calcResult->totalCost ?? 0.0;
            $this->optimizedEndBattery = $calcResult->endBattery ?? 0.0;
            $this->optimizedBatteryResults = $calcResult->batteryResults ?? [];

            $this->optimizedValid = $this->optimizedEndBattery >= 90.0;

            if ($this->optimizedValid) {
                return;
            }

            $periodToCharge = count($this->baseStrategy) - $i;
            if ($periodToCharge >= 0) {
                $this->baseStrategy[$periodToCharge]->strategy1 = true;
            }

            $i++;
        } while ($i < 6);

        $this->errors[] = sprintf(
            'Optimise end battery %.1f%% < 90%%',
            $this->optimizedEndBattery
        );
    }

    private function calculateFinalCost(): void
    {
        $isOptimizedBetter = $this->optimizedCost < $this->baselineCost;
        foreach ($this->baseStrategy as $strategy) {
            $strategy->strategy_manual = $strategy->strategy_manual
                ?: ($isOptimizedBetter ? $strategy->strategy1 : $strategy->strategy2);
        }

        $calculator = new StrategyCostCalculator();
        $request = new StrategyCostCalculatorRequest(
            $this->baseStrategy,
            $this->startBatteryPercentage,
            StrategyType::ManualStrategy
        );
        $calcResult = $calculator->calculateTotalCost($request);

        foreach ($this->baseStrategy as $index => $strategy) {
            $batteryResult = $calcResult->batteryResults[$index];
            $strategy->battery_percentage1 = $batteryResult->batteryPercentage;
            $strategy->battery_charge_amount = $batteryResult->chargeAmount;
            $strategy->battery_percentage_manual = $batteryResult->batteryPercentage;
            $strategy->import_amount = $batteryResult->importAmount;
            $strategy->export_amount = $batteryResult->exportAmount;
        }
    }

    private function upsertStrategy(): void
    {
        $updates = [];
        foreach ($this->baseStrategy as $strategy) {
            if (!$strategy->isDirty()) {
                continue;
            }

            $update = $strategy->toArray();
            // The forecast is a relationship and should not be persisted (possibly only a test requirement)
            if (isset($update['forecast'])) {
                unset($update['forecast']);
            }
            $updates[] = $update;
        }

        if (!empty($updates)) {
            Strategy::upsert(
                $updates,
                'id',
                [
                    'strategy1',
                    'strategy2',
                    'strategy_manual',
                    'battery_percentage_manual',
                    'battery_percentage1',
                    'battery_charge_amount',
                    'import_amount',
                    'export_amount',
                ]
            );
        }
    }

    public function getStrategy(CarbonInterface $start, CarbonInterface $end, int $limit): void
    {
        $this->baseStrategy = Strategy::query()
            ->with('forecast:id,period_end,pv_estimate')
            ->whereBetween('period', [$start, $end])
            ->limit($limit)
            ->orderBy('period')
            ->get([
                'id',
                'period',
                'consumption_average',
                'consumption_last_week',
                'consumption_manual',
                'import_value_inc_vat',
                'export_value_inc_vat',
                'strategy_manual',
            ]);
    }

    private function calculateBaseStrategyThreshold(): bool
    {
        $importCosts = $this->baseStrategy
            ->filter(fn ($strategy) => $strategy->import_value_inc_vat !== null)
            ->map(fn ($strategy) => $strategy->import_value_inc_vat)
            ->toArray();

        if (count($importCosts) === 0) {
            $this->errors[] = "No import costs found";
            return false;
        }

        $averageCost = array_sum($importCosts) / count($importCosts);
        $minCost = min($importCosts);
        $this->baseStrategyThreshold = (float) (($averageCost + $minCost + $minCost) / 3);

        return true;
    }
}
