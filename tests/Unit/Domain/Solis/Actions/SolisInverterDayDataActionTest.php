<?php

namespace Tests\Unit\Domain\Solis\Actions;

use App\Domain\Solis\Actions\SolisInverterDayDataAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SolisInverterDayDataActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('solis', [
            'key_id' => 'test-key-id',
            'key_secret' => 'test-key-secret',
            'api_url' => 'https://test.soliscloud.com:3333/',
            'inverter_id' => 'test-inverter-id',
        ]);
    }

    public function testExecuteFetchesParsesAndReturnsConsumptionData(): void
    {
        Http::fake([
            '*inverterDay*' => Http::response($this->mockSuccessfulApiResponse(), 200),
        ]);

        $action = new SolisInverterDayDataAction(date: '2023-06-27');

        $data = $action->execute();

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/v1/api/inverterDay')
                && $request['time'] === '2023-06-27'
                && $request['id'] === 'test-inverter-id';
        });

        $this->assertCount(1, $data);
        $this->assertEquals('2023-06-27 05:00:00', $data[0]['period']);
        $this->assertEquals(5.0, $data[0]['yield']);
        $this->assertEquals(1.0, $data[0]['to_grid']);
        $this->assertEquals(2.0, $data[0]['from_grid']);
        $this->assertEquals(3.0, $data[0]['consumption']);
        $this->assertEquals(51, $data[0]['battery_soc']);
    }

    public function testExecuteHandlesApiError(): void
    {
        Http::fake([
            '*inverterDay*' => Http::response(['success' => false, 'code' => '1'], 200),
        ]);

        $action = new SolisInverterDayDataAction(date: '2023-06-27');

        $this->assertEmpty($action->execute());
    }

    private function mockSuccessfulApiResponse(): array
    {
        return [
            'success' => true,
            'code' => '0',
            'msg' => 'success',
            'data' => [
                [
                    'timeStr' => '2023-06-27 05:05:00',
                    'pac' => 0.0,
                    'eToday' => 0.0,
                    'eTotal' => 100.0,
                    'batteryCapacitySoc' => 50,
                    'gridPurchasedTodayEnergy' => 0.0,
                    'gridSellTodayEnergy' => 0.0,
                    'homeLoadTodayEnergy' => 0.0,
                ],
                [
                    'timeStr' => '2023-06-27 05:25:00',
                    'pac' => 10.0,
                    'eToday' => 5.0,
                    'eTotal' => 105.0,
                    'batteryCapacitySoc' => 51,
                    'gridPurchasedTodayEnergy' => 2.0,
                    'gridSellTodayEnergy' => 1.0,
                    'homeLoadTodayEnergy' => 3.0,
                ],
            ],
        ];
    }
}
