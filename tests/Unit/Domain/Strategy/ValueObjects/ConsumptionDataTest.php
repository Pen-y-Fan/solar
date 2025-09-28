<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Strategy\ValueObjects;

use App\Domain\Strategy\ValueObjects\ConsumptionData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConsumptionDataTest extends TestCase
{
    public function testFromArrayAndToArrayRoundTripAndBestEstimatePriority(): void
    {
        $data = [
            'consumption_last_week' => 5.5,
            'consumption_average' => 4.2,
            'consumption_manual' => 6.1,
        ];

        $vo = ConsumptionData::fromArray($data);

        $this->assertSame($data, $vo->toArray());
        // manual takes precedence
        $this->assertSame(6.1, $vo->getBestEstimate());

        // remove manual -> last week
        $vo2 = new ConsumptionData(lastWeek: 5.5, average: 4.2, manual: null);
        $this->assertSame(5.5, $vo2->getBestEstimate());

        // only average
        $vo3 = new ConsumptionData(lastWeek: null, average: 4.2, manual: null);
        $this->assertSame(4.2, $vo3->getBestEstimate());

        // all null
        $vo4 = new ConsumptionData();
        $this->assertNull($vo4->getBestEstimate());
    }

    public function testNegativeValuesRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConsumptionData(lastWeek: -1);
    }
}
