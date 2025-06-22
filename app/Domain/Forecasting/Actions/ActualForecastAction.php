<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Actions;

use App\Domain\Forecasting\Models\ActualForecast;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActualForecastAction
{
    public function run()
    {
        Log::info('Start running Solcast actual forecast action');

        $lastForecast = ActualForecast::latest('updated_at')->first();

        throw_if(
            ! empty($lastForecast) && $lastForecast['updated_at'] >= now()->subHour(),
            sprintf(
                'Last updated in the hour, try again in %s',
                $lastForecast['updated_at']->addHour()->diffForHumans()
            )
        );

        $data = $this->getForecastData();

        ActualForecast::upsert(
            $data,
            uniqueBy: ['period_end'],
            update: ['pv_estimate']
        );
    }

    /**
     * @throws \Throwable
     */
    private function getForecastData()
    {
        $api = Config::get('solcast.api_key');
        $resourceId = Config::get('solcast.resource_id');

        $url = sprintf(
            'https://api.solcast.com.au/rooftop_sites/%s/estimated_actuals?format=json&hours=24',
            $resourceId
        );

        $headers = [
            'Authorization' => 'Bearer ' . $api,
        ];

        try {
            $response = Http::acceptJson()
                ->withHeaders($headers)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('There was a connection error trying to get Solcast forecast data:'
                . $e->getMessage());
        }

        $data = $response->json();
        Log::info(
            'Solcast Actual Forecast Action',
            [
                'successful' => $response->successful(),
                'json' => $data,
            ]
        );

        throw_if($response->failed(), 'Unsuccessful actual forecast, check the log file for more details.');

        return collect($data['estimated_actuals'])
            ->map(function ($item) {
                return [
                    'period_end' => Carbon::parse($item['period_end'])->timezone('UTC')->toDateTimeString(),
                    'pv_estimate' => $item['pv_estimate'],
                ];
            })->toArray();
    }
}
