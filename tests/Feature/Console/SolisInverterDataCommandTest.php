<?php

namespace Tests\Feature\Console\Commands;

use App\Domain\Energy\Models\Inverter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolisInverterDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testCommandFetchesAndDispatchesUpsertForGivenDate(): void
    {
        Config::set('solis.inverter_id', 'test-id');
        Http::fake([
            '*inverterDay*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $this->artisan('solis:inverter-data', ['date' => '2023-06-27'])
            ->expectsOutputToContain('Solis inverter data upsert completed for 2023-06-27')
            ->assertExitCode(0);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/v1/api/inverterDay')
                && $request['time'] === '2023-06-27';
        });
    }

    public function testCommandDefaultsToYesterday(): void
    {
        Config::set('solis.inverter_id', 'test-id');
        Http::fake([
            '*inverterDay*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $yesterday = Carbon::yesterday()->format('Y-m-d');
        $this->artisan('solis:inverter-data')
            ->expectsOutputToContain("Solis inverter data upsert completed for {$yesterday}")
            ->assertExitCode(0);
    }

    public function testUpsertsRecordsToDatabase(): void
    {
        Config::set('solis', [
            'key_id' => 'test-key',
            'key_secret' => 'test-secret',
            'api_url' => 'https://test.solis/',
            'inverter_id' => 'test-id',
        ]);

        $mockPoints = [
            [
                'timeStr' => '2023-06-27 05:05:00',
                'eToday' => 0.0,
                'gridPurchasedEnergy' => 0.0,
                'gridSellEnergy' => 0.0,
                'homeLoadEnergy' => 0.0,
                'batteryCapacitySoc' => 50,
            ],
            [
                'timeStr' => '2023-06-27 05:25:00',
                'eToday' => 5.0,
                'gridPurchasedEnergy' => 2.0,
                'gridSellEnergy' => 1.0,
                'homeLoadEnergy' => 3.0,
                'batteryCapacitySoc' => 51,
            ],
        ];

        Http::fake([
            '*inverterDay*' => Http::response([
                'success' => true,
                'data' => $mockPoints,
            ], 200),
        ]);

        $before = Inverter::count();

        $this->artisan('solis:inverter-data', ['date' => '2023-06-27'])
            ->assertExitCode(0);

        $this->assertGreaterThan($before, Inverter::count());
    }
}
