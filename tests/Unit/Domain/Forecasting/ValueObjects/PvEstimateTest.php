<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting\ValueObjects;

use App\Domain\Forecasting\ValueObjects\PvEstimate;
use PHPUnit\Framework\TestCase;

final class PvEstimateTest extends TestCase
{
    public function testFromArrayAndToArrayRoundTripWithAllFields(): void
    {
        $data = [
            'pv_estimate' => 5.5,
            'pv_estimate10' => 3.0,
            'pv_estimate90' => 8.0,
        ];

        $vo = PvEstimate::fromArray($data);

        $this->assertSame(5.5, $vo->estimate);
        $this->assertSame(3.0, $vo->estimate10);
        $this->assertSame(8.0, $vo->estimate90);

        $this->assertSame($data, $vo->toArray());
    }

    public function testFromArrayHandlesMissingBoundsAsNulls(): void
    {
        $vo = PvEstimate::fromArray(['pv_estimate' => 2.25]);

        $this->assertSame(2.25, $vo->estimate);
        $this->assertNull($vo->estimate10);
        $this->assertNull($vo->estimate90);

        $this->assertSame(['pv_estimate' => 2.25, 'pv_estimate10' => null, 'pv_estimate90' => null], $vo->toArray());
    }

    public function testToSingleArrayContainsOnlyMainEstimate(): void
    {
        $vo = new PvEstimate(estimate: 7.0, estimate10: 4.0, estimate90: 9.0);

        $this->assertSame(['pv_estimate' => 7.0], $vo->toSingleArray());
    }

    public function testFromSingleEstimateBuildsMinimalVo(): void
    {
        $vo = PvEstimate::fromSingleEstimate(1.5);

        $this->assertSame(1.5, $vo->estimate);
        $this->assertNull($vo->estimate10);
        $this->assertNull($vo->estimate90);
    }

    public function testConstructorThrowsOnNegativeValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PvEstimate(estimate: -0.1);
    }

    public function testConstructorThrowsOnNegativeEstimate10(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PvEstimate(estimate: 0.0, estimate10: -1.0);
    }

    public function testConstructorThrowsOnNegativeEstimate90(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PvEstimate(estimate: 0.0, estimate10: 0.0, estimate90: -0.01);
    }
}
