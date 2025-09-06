<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting;

use App\Domain\Forecasting\Actions\ActualForecastAction;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActualForecastActionTest extends TestCase
{
    use RefreshDatabase;

    public function testExecuteSuccessReturnsActionResult(): void
    {
        Config::set('solcast.api_key', 'test');
        Config::set('solcast.resource_id', 'res_123');

        $now = now()->startOfHour();

        Http::fake([
            'https://api.solcast.com.au/rooftop_sites/*/estimated_actuals*' => Http::response([
                'estimated_actuals' => [
                    [
                        'period_end' => $now->copy()->toIso8601String(),
                        'pv_estimate' => 1.23,
                    ],
                ],
            ], 200),
        ]);

        $result = (new ActualForecastAction())->execute();

        $this->assertInstanceOf(ActionResult::class, $result);
    }
}
