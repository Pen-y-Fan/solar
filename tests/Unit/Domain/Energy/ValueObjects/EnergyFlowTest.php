<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy\ValueObjects;

use App\Domain\Energy\Models\Inverter;
use App\Domain\Energy\ValueObjects\EnergyFlow;
use PHPUnit\Framework\TestCase;

class EnergyFlowTest extends TestCase
{
    public function testFromArrayAndToArrayRoundTrip(): void
    {
        $data = [
            'yield' => 2.5,
            'to_grid' => 1.0,
            'from_grid' => 0.4,
            'consumption' => 1.9,
        ];

        $vo = EnergyFlow::fromArray($data);
        $this->assertSame($data, $vo->toArray());
        $this->assertSame(2.5, $vo->yield);
        $this->assertSame(1.0, $vo->toGrid);
        $this->assertSame(0.4, $vo->fromGrid);
        $this->assertSame(1.9, $vo->consumption);
    }

    public function testDefaultsWhenKeysMissing(): void
    {
        $vo = EnergyFlow::fromArray([]);
        $this->assertSame(0.0, $vo->yield);
        $this->assertSame(0.0, $vo->toGrid);
        $this->assertSame(0.0, $vo->fromGrid);
        $this->assertSame(0.0, $vo->consumption);
    }

    public function testSelfConsumptionIsYieldMinusToGrid(): void
    {
        $vo = new EnergyFlow(yield: 3.2, toGrid: 1.1, fromGrid: 0.7, consumption: 2.5);
        $this->assertEqualsWithDelta(2.1, $vo->getSelfConsumption(), 1e-9);
    }

    public function testSelfSufficiencyIsPercentageOfConsumption(): void
    {
        $vo = new EnergyFlow(yield: 3.0, toGrid: 1.0, fromGrid: 2.0, consumption: 4.0);
        // self consumption = 2.0, sufficiency = 2/4 * 100 = 50%
        $this->assertEqualsWithDelta(50.0, $vo->getSelfSufficiency(), 1e-9);
    }

    public function testSelfSufficiencyWhenConsumptionZeroIsZero(): void
    {
        $vo = new EnergyFlow(yield: 1.0, toGrid: 0.1, fromGrid: 0.0, consumption: 0.0);
        $this->assertSame(0.0, $vo->getSelfSufficiency());
    }

    public function testNetFlowPositiveForExportAndNegativeForImport(): void
    {
        $exporter = new EnergyFlow(yield: 1.0, toGrid: 0.8, fromGrid: 0.2, consumption: 0.4);
        $importer = new EnergyFlow(yield: 0.1, toGrid: 0.0, fromGrid: 0.6, consumption: 0.7);

        $this->assertEqualsWithDelta(0.6, $exporter->getNetFlow(), 1e-9); // net export
        $this->assertEqualsWithDelta(-0.6, $importer->getNetFlow(), 1e-9); // net import
    }

    public function testInverterModelEnergyFlowAccessorAndMutatorRoundTrip(): void
    {
        $model = new Inverter();

        $vo = new EnergyFlow(yield: 2.25, toGrid: 0.75, fromGrid: 0.5, consumption: 2.0);
        // Avoid magic property assignment to @property-read in test; call mutator directly
        $model->setEnergyFlowAttribute($vo);

        $this->assertSame(2.25, $model->yield);
        $this->assertSame(0.75, $model->to_grid);
        $this->assertSame(0.5, $model->from_grid);
        $this->assertSame(2.0, $model->consumption);

        // Getting VO back via accessor
        $roundTripped = $model->energyFlow; // getEnergyFlowAttribute
        $this->assertInstanceOf(EnergyFlow::class, $roundTripped);
        $this->assertSame($vo->toArray(), $roundTripped->toArray());
    }
}
