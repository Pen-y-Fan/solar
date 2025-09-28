<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting;

use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Forecasting\ValueObjects\PvEstimate;
use PHPUnit\Framework\TestCase;

final class ForecastModelTest extends TestCase
{
    public function testPvEstimateVoRoundTrip(): void
    {
        $model = new Forecast();

        // Seed raw attributes as if from DB
        $model->setRawAttributes([
            'pv_estimate' => 3.5,
            'pv_estimate10' => 1.2,
            'pv_estimate90' => 6.7,
        ], true);

        $vo = $model->getPvEstimateValueObject();
        $this->assertEqualsWithDelta(3.5, $vo->estimate, 1e-6);
        $this->assertEqualsWithDelta(1.2, $vo->estimate10, 1e-6);
        $this->assertEqualsWithDelta(6.7, $vo->estimate90, 1e-6);

        // Now mutate via VO setter and ensure attributes reflect it
        $newVo = new PvEstimate(estimate: 4.0, estimate10: 2.0, estimate90: 8.0);
        $model->setPvEstimateValueObject($newVo);

        $vo2 = $model->getPvEstimateValueObject();
        $this->assertEqualsWithDelta(4.0, $vo2->estimate, 1e-6);
        $this->assertEqualsWithDelta(2.0, $vo2->estimate10, 1e-6);
        $this->assertEqualsWithDelta(8.0, $vo2->estimate90, 1e-6);
    }
}
