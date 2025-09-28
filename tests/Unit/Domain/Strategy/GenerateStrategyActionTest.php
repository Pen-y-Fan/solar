<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Strategy;

use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use App\Domain\Energy\ValueObjects\InverterConsumptionData;
use App\Domain\Strategy\Actions\GenerateStrategyAction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class GenerateStrategyActionTest extends TestCase
{
    private function makeAction(): GenerateStrategyAction
    {
        $stubRepo = new class implements InverterRepositoryInterface {
            public function getAverageConsumptionByTime(
                CarbonInterface $startDate
            ): Collection {
                return collect();
            }
            public function getConsumptionForDateRange(
                CarbonInterface $startDate,
                CarbonInterface $endDate
            ): Collection {
                return collect();
            }
        };

        return new GenerateStrategyAction($stubRepo);
    }

    private function forecastEntry(string $timeHi, ?float $import, ?float $export, float $pv): object
    {
        $period = CarbonImmutable::createFromFormat('Hi', $timeHi, 'UTC');

        // Create simple value objects with value_inc_vat properties like Eloquent models would expose
        $importCost = $import === null ? null : (object) ['value_inc_vat' => $import];
        $exportCost = $export === null ? null : (object) ['value_inc_vat' => $export];

        return (object) [
            'period_end' => $period,
            'importCost' => $importCost,
            'exportCost' => $exportCost,
            'pv_estimate' => $pv,
        ];
    }

    private function consumption(string $timeHis, float $value): InverterConsumptionData
    {
        return new InverterConsumptionData(time: $timeHis, value: $value);
    }

    public function testGetConsumptionUsesCostVoAndBoundsBattery(): void
    {
        $action = $this->makeAction();

        // Arrange: 4 half-hour periods with varying import costs
        $forecasts = [
            $this->forecastEntry('0030', 10.0, 5.0, 0.0),   // cheap -> should charge
            $this->forecastEntry('0100', 50.0, 5.0, 0.0),   // expensive -> discharge/hold depending on estimate
            $this->forecastEntry('0130', 10.0, 5.0, 0.0),   // cheap -> charge
            $this->forecastEntry('0200', null, 5.0, 0.0),   // missing import cost -> treated as 0 -> cheap -> charge
        ];

        // Consumption data for those times (H:i:s format)
        $consumptions = new Collection([
            $this->consumption('00:30:00', 0.5),
            $this->consumption('01:00:00', 0.5),
            $this->consumption('01:30:00', 0.5),
            $this->consumption('02:00:00', 0.5),
        ]);

        // Prime internal threshold to a value between 10 and 50 to force selective charging
        $refChargeStrategy = new ReflectionProperty($action, 'chargeStrategy');
        $refChargeStrategy->setAccessible(true);
        $refChargeStrategy->setValue($action, 20.0);

        // Act
        $result = $action->getConsumption($forecasts, $consumptions);

        // Assert shape and VO-derived fields
        $this->assertSame(4, $result->count());

        // 00:30 should charge (10 < 20)
        $p0030 = $result->get('0030');
        $this->assertTrue($p0030['charging']);
        $this->assertEqualsWithDelta(10.0, $p0030['import_value_inc_vat'], 1e-6);
        $this->assertGreaterThanOrEqual(0, $p0030['battery_percentage']);
        $this->assertLessThanOrEqual(100, $p0030['battery_percentage']);

        // 01:00 should not charge (50 >= 20)
        $p0100 = $result->get('0100');
        $this->assertFalse($p0100['charging']);
        $this->assertEqualsWithDelta(50.0, $p0100['import_value_inc_vat'], 1e-6);
        $this->assertGreaterThanOrEqual(0, $p0100['battery_percentage']);
        $this->assertLessThanOrEqual(100, $p0100['battery_percentage']);

        // 01:30 charge
        $this->assertTrue($result->get('0130')['charging']);

        // 02:00 missing import cost treated as 0 -> charge
        $this->assertTrue($result->get('0200')['charging']);
    }

    public function testGetConsumptionHandlesMissingConsumptionDefaultsToZero(): void
    {
        $action = $this->makeAction();

        $forecasts = [
            $this->forecastEntry('0030', 15.0, 5.0, 0.0),
        ];

        // No matching consumption time provided
        $consumptions = new Collection([
            $this->consumption('01:00:00', 0.5),
        ]);

        // Set threshold above 15 so it charges only when cheaper than 15
        $refChargeStrategy = new ReflectionProperty($action, 'chargeStrategy');
        $refChargeStrategy->setAccessible(true);
        $refChargeStrategy->setValue($action, 20.0);

        $result = $action->getConsumption($forecasts, $consumptions);

        $row = $result->get('0030');
        $this->assertSame(0, $row['consumption']); // default to zero
        $this->assertTrue($row['charging']);       // 15 < 20
    }
}
