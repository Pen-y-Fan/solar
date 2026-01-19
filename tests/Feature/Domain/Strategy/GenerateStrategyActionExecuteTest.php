<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Strategy;

use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use App\Domain\Energy\ValueObjects\InverterConsumptionData;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\Actions\GenerateStrategyAction;
use App\Domain\Strategy\Models\Strategy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

final class GenerateStrategyActionExecuteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindInverterRepoReturning(Collection $avg, Collection $week): void
    {
        $stub = new class ($avg, $week) implements InverterRepositoryInterface {
            public function __construct(private Collection $avg, private Collection $week)
            {
            }

            public function getAverageConsumptionByTime(CarbonInterface $startDate): Collection
            {
                return $this->avg;
            }

            public function getConsumptionForDateRange(CarbonInterface $startDate, CarbonInterface $endDate): Collection
            {
                return $this->week;
            }
        };

        $this->app->instance(InverterRepositoryInterface::class, $stub);
    }

    private function makeConsumption(string $his, float $val): InverterConsumptionData
    {
        return new InverterConsumptionData(time: $his, value: $val);
    }

    public function testExecuteCreatesStrategiesWithVoConsistentFields(): void
    {
        // Arrange
        // Set in BST to confirm UTC conversion works.
        $start = CarbonImmutable::create(2025, 7, 1, 16, 00, 0, 'Europe/London')
            ->timezone('UTC');

        $this->generateForecastData($start);
        $this->generateAgileData($start);
        $this->generateConsumptionData($start);

        $action = app(GenerateStrategyAction::class);
        // The filter for 'tomorrow' starts from 16:00 today to 16:00 the next day, add a day
        $action->filter = $start->clone()->addDay()->format('Y-m-d');

        $lowPeriods = [
            'nightCheap1' => $start->clone()->setTime(21, 00), // Remember UTC,
            'nightCheap2' => $start->clone()->setTime(22, 30),
            'nightCheap3' => $start->clone()->addDay()->setTime(03, 30),
            'dayCheap1'   => $start->clone()->addDay()->setTime(11, 30),
            'dayCheap2'   => $start->clone()->addDay()->setTime(13, 30),
            'dayCheap3'   => $start->clone()->addDay()->setTime(14, 30),
        ];

        // Act
        $result = $action->execute();

        // Assert
        $this->assertStringStartsWith("48 strategies generated", $result->getMessage());
        $this->assertTrue($result->isSuccess(), 'ActionResult should be ok');

        // Assert all 48 strategies persisted
        $this->assertDatabaseCount(Strategy::class, 48);

        foreach ($lowPeriods as $key => $period) {
            $lowStrategy = Strategy::where('period', $period)->firstOrFail();
            $this->assertTrue($lowStrategy->strategy_manual, sprintf("Low period %s should be charging", $key));
            $this->assertTrue($lowStrategy->strategy1, sprintf("Low period %s should be charging", $key));
        }

        // Load back and assert VO-consistent fields
        $s1 = Strategy::where('period', $start)->firstOrFail();
        $s2 = Strategy::where('period', $start->addMinutes(30))->firstOrFail();

        $this->assertEqualsWithDelta(50.00, $s1->import_value_inc_vat, 1e-6);
        $this->assertEqualsWithDelta(5.00, $s1->export_value_inc_vat, 1e-6);
        $this->assertEqualsWithDelta(50.00, $s2->import_value_inc_vat, 1e-6);
        $this->assertEqualsWithDelta(5.00, $s2->export_value_inc_vat, 1e-6);
        $this->assertFalse($s1->strategy1);
        $this->assertFalse($s1->strategy2);
        $this->assertFalse($s1->strategy_manual);
        $this->assertFalse($s2->strategy1);
        $this->assertFalse($s2->strategy2);
        $this->assertFalse($s2->strategy_manual);

        // Consumption fields saved from repository data
        $this->assertEqualsWithDelta(0.5, $s1->consumption_average, 1e-6);
        $this->assertEqualsWithDelta(0.4, $s1->consumption_last_week, 1e-6);

        // CostData VO derived costs available via accessor
        $cd1 = $s1->getCostDataValueObject();
        $this->assertEqualsWithDelta(25, $cd1->consumptionAverageCost, 1e-6);

        // No charge at expensive rates
        $expensiveStrategies = Strategy::where('import_value_inc_vat', 50.0)->get();
        $this->assertCount(6, $expensiveStrategies);
        foreach ($expensiveStrategies as $strategy) {
            $this->assertFalse(
                $strategy->strategy1,
                sprintf("Expensive period %s should not be charging", $strategy->period->format('Y-m-d H:i'))
            );
        }

        $strategyLast = Strategy::orderBy('period', 'DESC')->firstOrFail();
        $this->assertGreaterThanOrEqual(
            90,
            $strategyLast->battery_percentage_manual,
            "The battery should be over 90% before 4pm"
        );
        $this->assertTrue($strategyLast->strategy_manual, "The last strategy should charge before 4pm");
        $this->assertTrue($strategyLast->strategy1, "The last strategy should charge before 4pm");
        $this->assertTrue($strategyLast->strategy2, "The last strategy should charge before 4pm");
    }

    public function testExecuteReturnsFailureWhenNoForecasts(): void
    {
        $this->bindInverterRepoReturning(collect(), collect());

        $action = app(GenerateStrategyAction::class);
        $action->filter = 'today';
        $result = $action->execute();

        $this->assertFalse($result->isSuccess());
        $this->assertDatabaseCount(Strategy::class, 0);
    }

    public function testExecuteReturnsFailureWhenNoAverageConsumption(): void
    {
        // Seed a forecast but make repo return no averages
        $f = Forecast::factory()->create([
            'period_end'    => now()->setTimezone('Europe/London')->startOfDay()->setTimezone('UTC')->addMinutes(30),
            'pv_estimate'   => 0.0,
            'pv_estimate10' => 0.0,
            'pv_estimate90' => 0.0,
        ]);
        AgileImport::create([
            'valid_from'    => $f->period_end,
            'valid_to'      => $f->period_end->clone()->addMinutes(30),
            'value_exc_vat' => 0.00,
            'value_inc_vat' => 10.00,
        ]);

        $this->bindInverterRepoReturning(collect(), collect());

        $action = app(GenerateStrategyAction::class);
        $action->filter = 'today';
        $result = $action->execute();

        $this->assertFalse($result->isSuccess());
        $this->assertDatabaseCount(Strategy::class, 0);
    }

    private function generateForecastData(CarbonImmutable $start): void
    {
        $forecastData = [];
        $baseForecast = [
            'period_end'    => null,
            'pv_estimate'   => 0.5,
            'pv_estimate10' => 0.0,
            'pv_estimate90' => 0.0,
        ];

        $pvOffset = 0;
        $nightStart = $start->clone()->addHours(5); // 21:00
        $nightEnd = $nightStart->clone()->addHours(8);
        $midday = $nightEnd->clone()->addHours(6);

        // create 48 periods using CarbonPeriod 30 min intervals starting from $start, ending one day later
        $periods = new CarbonPeriod($start, '30 minutes', 48);
        foreach ($periods as $period) {
            if ($period > $nightEnd) {
                $pvOffset += $period > $midday ? -0.1 : 0.1;
            }

            $baseForecast['pv_estimate'] = $pvOffset;
            $baseForecast['period_end'] = $period;
            $forecastData[] = $baseForecast;
        }

        $result = Forecast::upsert($forecastData, 'period_end');

        $forecasts = Forecast::count();

        $this->assertSame(48, $result);
        $this->assertSame(48, $forecasts, "Should be 48");
    }

    private function generateAgileData(CarbonImmutable $start): void
    {
        $agileImportData = [];
        $agileExportData = [];

        $baseAgileImport = [
            'valid_from'    => null,
            'valid_to'      => null,
            'value_exc_vat' => 0.00,
            'value_inc_vat' => 10.00,
        ];

        $baseAgileExport = [
            'valid_from'    => null,
            'valid_to'      => null,
            'value_exc_vat' => 0.00,
            'value_inc_vat' => 5.00,
        ];

        $periods = new CarbonPeriod($start, '30 minutes', 48);

        $expensiveStart = $start->clone();
        $expensiveEnd = $expensiveStart->addHours(3);
        $nightCheap1 = $start->clone()->setTime(21, 00); // Remember UTC!
        $nightCheap2 = $start->clone()->setTime(22, 30);
        $nightCheap3 = $start->clone()->addDay()->setTime(03, 30);
        $dayCheap1 = $start->clone()->addDay()->setTime(11, 30);
        $dayCheap2 = $start->clone()->addDay()->setTime(13, 30);
        $dayCheap3 = $start->clone()->addDay()->setTime(14, 30);

        foreach ($periods as $period) {
            switch ($period) {
                case $period >= $expensiveStart && $period < $expensiveEnd:
                    $importCost = 50.0;
                    break;
                case $period == $nightCheap1 || $period == $nightCheap2 || $period == $nightCheap3:
                    $importCost = 10;
                    break;
                case $period == $dayCheap1 || $period == $dayCheap2 || $period == $dayCheap3:
                    $importCost = 15;
                    break;
                default:
                    $importCost = 20;
                    break;
            }

            $baseAgileImport['valid_from'] = $period;
            $baseAgileImport['value_inc_vat'] = $importCost;
            $baseAgileImport['valid_to'] = $period->clone()->addMinutes(30);
            $agileImportData[] = $baseAgileImport;

            $baseAgileExport['valid_from'] = $period;
            $baseAgileExport['valid_to'] = $period->clone()->addMinutes(30);
            $agileExportData[] = $baseAgileExport;
        }

        $resultAgileImport = AgileImport::upsert($agileImportData, 'valid_from');
        $resultAgileExport = AgileExport::upsert($agileExportData, 'valid_from');

        $resultAgileImportCount = AgileImport::count();
        $this->assertSame(48, $resultAgileImport);
        $this->assertSame(48, $resultAgileImportCount, "Should be 48");

        $resultAgileExportCount = AgileExport::count();
        $this->assertSame(48, $resultAgileExport);
        $this->assertSame(48, $resultAgileExportCount, "Should be 48");
    }

    private function generateConsumptionData(CarbonImmutable $start): void
    {
        $avg = collect();
        $week = collect();
        $periods = new CarbonPeriod($start, '30 minutes', 48);
        foreach ($periods as $period) {
            $avg->add($this->makeConsumption($period->format('H:i:s'), 0.5));
            $week->add($this->makeConsumption($period->format('H:i:s'), 0.4));
        }
        $this->bindInverterRepoReturning($avg, $week);
    }
}
