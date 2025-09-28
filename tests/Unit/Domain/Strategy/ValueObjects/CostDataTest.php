<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Strategy\ValueObjects;

use App\Domain\Strategy\ValueObjects\CostData;
use PHPUnit\Framework\TestCase;

class CostDataTest extends TestCase
{
    public function testFromArrayAndToArrayRoundTrip(): void
    {
        $data = [
            'import_value_inc_vat' => 0.345,
            'export_value_inc_vat' => 0.12,
            'consumption_average_cost' => 0.25,
            'consumption_last_week_cost' => 0.22,
        ];

        $vo = CostData::fromArray($data);

        $this->assertSame($data, $vo->toArray());
        $this->assertSame(0.345 - 0.12, $vo->getNetCost());
        $this->assertTrue($vo->isImportCostHigher());
        $this->assertSame(0.22, $vo->getBestConsumptionCostEstimate());
    }

    public function testNullSafetyWhenValuesMissing(): void
    {
        $vo = new CostData(
            importValueIncVat: null,
            exportValueIncVat: null,
            consumptionAverageCost: null,
            consumptionLastWeekCost: null
        );

        $this->assertNull($vo->getNetCost());
        $this->assertNull($vo->isImportCostHigher());
        $this->assertNull($vo->getBestConsumptionCostEstimate());
    }

    public function testBestConsumptionFallsBackToAverage(): void
    {
        $vo = new CostData(
            importValueIncVat: 0.3,
            exportValueIncVat: 0.1,
            consumptionAverageCost: 0.2,
            consumptionLastWeekCost: null
        );

        $this->assertSame(0.2, $vo->getBestConsumptionCostEstimate());
    }
}
