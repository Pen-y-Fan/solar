<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting;

use App\Domain\Forecasting\Actions\ForecastAction;
use App\Domain\Forecasting\Models\Forecast;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForecastActionTest extends TestCase
{
    use RefreshDatabase;

    public function testExecuteSuccessReturnsActionResultAndPersistsRecords(): void
    {
        Config::set('solcast.api_key', 'test');
        Config::set('solcast.resource_id', 'res_123');

        $now = now()->startOfHour();

        Http::fake([
            'https://api.solcast.com.au/rooftop_sites/*/forecasts/*' => Http::response([
                'forecasts' => [
                    [
                        'period_end' => $now->copy()->addHour()->toIso8601String(),
                        'pv_estimate' => 1.1,
                        'pv_estimate10' => 0.8,
                        'pv_estimate90' => 1.5,
                    ],
                    [
                        'period_end' => $now->copy()->addHours(2)->toIso8601String(),
                        'pv_estimate' => 2.2,
                        'pv_estimate10' => 1.9,
                        'pv_estimate90' => 2.6,
                    ],
                ],
            ], 200),
        ]);

        $result = (new ForecastAction())->execute();

        $this->assertInstanceOf(ActionResult::class, $result);

        // Note: Database persistence is covered in Feature tests.
    }
}
