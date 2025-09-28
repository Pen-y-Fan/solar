<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting;

use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\Forecasting\ValueObjects\PvEstimate;
use PHPUnit\Framework\TestCase;

final class ActualForecastModelTest extends TestCase
{
    public function testPvEstimateVoRoundTrip(): void
    {
        $model = new ActualForecast();

        // Seed raw attributes as if from DB
        $model->setRawAttributes([
            'pv_estimate' => 5.5,
        ], true);

        $vo = $model->getPvEstimateValueObject();
        $this->assertEqualsWithDelta(5.5, $vo->estimate, 1e-6);
        $this->assertNull($vo->estimate10);
        $this->assertNull($vo->estimate90);

        // Now mutate via VO setter and ensure attributes reflect it
        $newVo = PvEstimate::fromSingleEstimate(7.25);
        $model->setPvEstimateValueObject($newVo);

        $vo2 = $model->getPvEstimateValueObject();
        $this->assertEqualsWithDelta(7.25, $vo2->estimate, 1e-6);
        $this->assertNull($vo2->estimate10);
        $this->assertNull($vo2->estimate90);
    }
}
