<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy;

use App\Domain\Energy\ValueObjects\MonetaryValue;
use PHPUnit\Framework\TestCase;

class MonetaryValueTest extends TestCase
{
    public function testFromArrayAndToArrayRoundTrip(): void
    {
        $data = [
            'value_exc_vat' => 0.1234,
            'value_inc_vat' => 0.14808,
        ];

        $vo = MonetaryValue::fromArray($data);
        $this->assertSame(0.1234, $vo->excVat);
        $this->assertSame(0.14808, $vo->incVat);

        $this->assertSame($data, $vo->toArray());
    }

    public function testVatAmountAndRateWhenBothValuesPresent(): void
    {
        $vo = new MonetaryValue(excVat: 0.20, incVat: 0.24);
        $this->assertEqualsWithDelta(0.04, $vo->getVatAmount(), 1e-9);
        // Rate = (0.24/0.20 - 1) * 100 = 20%
        $this->assertEqualsWithDelta(20.0, $vo->getVatRate(), 1e-9);
    }

    public function testVatHelpersReturnNullWhenMissingValues(): void
    {
        $this->assertNull((new MonetaryValue(excVat: null, incVat: 0.24))->getVatAmount());
        $this->assertNull((new MonetaryValue(excVat: 0.20, incVat: null))->getVatAmount());

        $this->assertNull((new MonetaryValue(excVat: null, incVat: 0.24))->getVatRate());
        $this->assertNull((new MonetaryValue(excVat: 0.20, incVat: null))->getVatRate());
    }

    public function testVatRateReturnsNullWhenExcVatIsZero(): void
    {
        $vo = new MonetaryValue(excVat: 0.0, incVat: 0.10);
        $this->assertNull($vo->getVatRate());
    }

    public function testHandlesNegativeValuesWithoutThrowingAndComputesDifferences(): void
    {
        // Some upstream feeds can include negative unit rates (e.g., export credits). Ensure math remains consistent.
        $vo = new MonetaryValue(excVat: -0.05, incVat: -0.06);
        $this->assertEqualsWithDelta(-0.01, $vo->getVatAmount(), 1e-9);
        // Rate = ((-0.06 / -0.05) - 1) * 100 = 20%
        $this->assertEqualsWithDelta(20.0, $vo->getVatRate(), 1e-9);
    }
}
