<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy\ValueObjects;

use App\Domain\Energy\ValueObjects\InverterConsumptionData;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class InverterConsumptionDataTest extends TestCase
{
    public function testFromArrayBuildsVoAndToArrayRoundTrips(): void
    {
        $data = [
            'time' => '07:15:00',
            'value' => 0.42,
        ];

        $vo = InverterConsumptionData::fromArray($data);

        $this->assertSame('07:15:00', $vo->time);
        $this->assertSame(0.42, $vo->value);
        $this->assertSame($data, $vo->toArray());
    }

    public function testFromCarbonFormatsTimeHmsAndPreservesValue(): void
    {
        $dt = Carbon::create(2025, 9, 28, 19, 32, 5, 'UTC');

        $vo = InverterConsumptionData::fromCarbon($dt, 1.2345);

        $this->assertSame('19:32:05', $vo->time);
        $this->assertSame(1.2345, $vo->value);
    }

    public function testPropertiesAreReadonlyAndImmutable(): void
    {
        $vo = new InverterConsumptionData('00:00:00', 0.0);

        $this->assertSame('00:00:00', $vo->time);
        $this->assertSame(0.0, $vo->value);
    }
}
