<?php

namespace Tests\Unit\Domain\Forecasting;

use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\Forecasting\ValueObjects\PvEstimate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ActualForecastModelBoundaryTest extends TestCase
{
    public function testSetPvEstimateThrowsOnNegativeSingleEstimate(): void
    {
        $actual = new ActualForecast();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PV estimate cannot be negative');

        $vo = PvEstimate::fromSingleEstimate(-0.01);
        $actual->setPvEstimateValueObject($vo);
    }
}
