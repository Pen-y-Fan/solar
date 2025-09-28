<?php

namespace Tests\Unit\Domain\Forecasting;

use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Forecasting\ValueObjects\PvEstimate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ForecastModelBoundaryTest extends TestCase
{
    public function testSetPvEstimateThrowsOnNegativeValues(): void
    {
        $forecast = new Forecast();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PV estimate cannot be negative');

        // Constructing the VO with a negative estimate should throw
        $vo = new PvEstimate(estimate: -1.0, estimate10: 0.0, estimate90: 1.0);
        $forecast->setPvEstimateValueObject($vo);
    }

    public function testSetPvEstimateThrowsOnNegativeEstimate10(): void
    {
        $forecast = new Forecast();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PV estimate 10th percentile cannot be negative');

        $vo = new PvEstimate(estimate: 0.0, estimate10: -0.5, estimate90: 1.0);
        $forecast->setPvEstimateValueObject($vo);
    }

    public function testSetPvEstimateThrowsOnNegativeEstimate90(): void
    {
        $forecast = new Forecast();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PV estimate 90th percentile cannot be negative');

        $vo = new PvEstimate(estimate: 0.0, estimate10: 0.5, estimate90: -1.0);
        $forecast->setPvEstimateValueObject($vo);
    }
}
