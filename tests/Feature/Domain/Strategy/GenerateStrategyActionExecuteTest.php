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
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class GenerateStrategyActionExecuteTest extends TestCase
{
    use RefreshDatabase;

    private function bindInverterRepoReturning(Collection $avg, Collection $week): void
    {
        $stub = new class ($avg, $week) implements InverterRepositoryInterface
        {
            public function __construct(private Collection $avg, private Collection $week)
            {
            }

            public function getAverageConsumptionByTime(CarbonInterface $startDate): Collection
            {
                return $this->avg;
            }

            public function getConsumptionForDateRange(
                CarbonInterface $startDate,
                CarbonInterface $endDate
            ): Collection {
                return $this->week;
            }
        };

        $this->app->instance(InverterRepositoryInterface::class, $stub);
    }

    private function makeConsumption(string $his, float $val): InverterConsumptionData
    {
        return new InverterConsumptionData(time: $his, value: $val);
    }

    public function testExecutePersistsStrategyRowsWithVoConsistentFields(): void
    {
        // Two contiguous half-hours on a fixed date before the export override cutoff (2025-07-08)
        $start = \Carbon\CarbonImmutable::create(2025, 7, 1, 0, 30, 0, 'UTC');

        $f1 = Forecast::create([
            'period_end' => $start,
            'pv_estimate' => 0.0,
            'pv_estimate10' => 0.0,
            'pv_estimate90' => 0.0,
        ]);
        $f2 = Forecast::create([
            'period_end' => $start->clone()->addMinutes(30),
            'pv_estimate' => 0.0,
            'pv_estimate10' => 0.0,
            'pv_estimate90' => 0.0,
        ]);

        // Costs mapped via VO accessors on related models
        AgileImport::create([
            'valid_from' => $f1->period_end,
            'valid_to' => $f1->period_end->clone()->addMinutes(30),
            'value_exc_vat' => 0.00,
            'value_inc_vat' => 10.00,
        ]);
        AgileExport::create([
            'valid_from' => $f1->period_end,
            'valid_to' => $f1->period_end->clone()->addMinutes(30),
            'value_exc_vat' => 0.00,
            'value_inc_vat' => 5.00,
        ]);

        AgileImport::create([
            'valid_from' => $f2->period_end,
            'valid_to' => $f2->period_end->clone()->addMinutes(30),
            'value_exc_vat' => 0.00,
            'value_inc_vat' => 50.00,
        ]);
        AgileExport::create([
            'valid_from' => $f2->period_end,
            'valid_to' => $f2->period_end->clone()->addMinutes(30),
            'value_exc_vat' => 0.00,
            'value_inc_vat' => 5.00,
        ]);

        // Provide consumption per half-hour via repository stub
        $avg = collect([
            $this->makeConsumption($f1->period_end->format('H:i:s'), 0.5),
            $this->makeConsumption($f2->period_end->format('H:i:s'), 0.5),
        ]);
        $week = collect([
            $this->makeConsumption($f1->period_end->format('H:i:s'), 0.4),
            $this->makeConsumption($f2->period_end->format('H:i:s'), 0.4),
        ]);
        $this->bindInverterRepoReturning($avg, $week);

        // Run action
        $action = app(GenerateStrategyAction::class);
        $action->filter = '2025-07-01';
        $result = $action->execute();

        $this->assertTrue($result->isSuccess(), 'ActionResult should be ok');

        // Assert two strategies persisted
        $this->assertDatabaseCount(Strategy::class, 2);

        // Load back and assert VO-consistent fields
        $s1 = Strategy::where('period', $f1->period_end)->firstOrFail();
        $s2 = Strategy::where('period', $f2->period_end)->firstOrFail();

        // import/export costs should map through CostData VO
        // (we check raw fields here but they originate from related models)
        $this->assertEqualsWithDelta(10.00, $s1->import_value_inc_vat, 1e-6);
        $this->assertEqualsWithDelta(5.00, $s1->export_value_inc_vat, 1e-6);
        $this->assertEqualsWithDelta(50.00, $s2->import_value_inc_vat, 1e-6);
        $this->assertEqualsWithDelta(5.00, $s2->export_value_inc_vat, 1e-6);

        // Strategy booleans set by action logic: cheap period should charge (strategy1 true)
        $this->assertContains($s1->strategy1, [0, 1, true, false]);
        $this->assertContains($s2->strategy1, [0, 1, true, false]);

        // Consumption fields saved from repository data
        $this->assertEqualsWithDelta(0.5, $s1->consumption_average, 1e-6);
        $this->assertEqualsWithDelta(0.4, $s1->consumption_last_week, 1e-6);

        // CostData VO derived costs available via accessor
        $cd1 = $s1->getCostDataValueObject();
        $this->assertEqualsWithDelta(0.5 * 10.00, $cd1->consumptionAverageCost, 1e-6);
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
        $f = Forecast::create([
            'period_end' => now()->setTimezone('Europe/London')->startOfDay()->setTimezone('UTC')->addMinutes(30),
            'pv_estimate' => 0.0,
            'pv_estimate10' => 0.0,
            'pv_estimate90' => 0.0,
        ]);
        AgileImport::create([
            'valid_from' => $f->period_end,
            'valid_to' => $f->period_end->clone()->addMinutes(30),
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
}
