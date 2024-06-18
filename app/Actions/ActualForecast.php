<?php

declare(strict_types=1);


namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActualForecast
{
    public function run()
    {
        Log::info('Start running Solcast actual forecast action');
        // check the last run (latest updated at), return if < 1 hour
        $lastForecast = \App\Models\ActualForecast::latest('updated_at')->first();

        throw_if(!empty($lastForecast) && $lastForecast['updated_at'] >= now()->subHour(),
            sprintf(
                'Last updated in the hour, try again in %s',
                $lastForecast['updated_at']->addHour()->diffForHumans()
            )
        );

        // fetch the latest forecast data
        $data = $this->getForecastData();
        // $data = $this->getPreviousData();

        // save it to the database
        \App\Models\ActualForecast::upsert(
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
            'Authorization' => 'Bearer ' . $api
        ];

        try {
            $response = Http::acceptJson()
                ->withHeaders($headers)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('There was a connection error trying to get Solcast forecast data:' . $e->getMessage());
        }

        $data = $response->json();
        Log::info('Solcast Actual Forecast Action',
            [
                'successful' => $response->successful(),
                'json' => $data
            ]);

        throw_if($response->failed() || str_contains($data, "response_status"), "Unsuccessful actual forecast, check the log file for more details.");

        return collect(json_decode($data, true, 512, JSON_THROW_ON_ERROR)['estimated_actuals'])
            ->map(function ($item) {
                return [
                    "period_end" => Carbon::parse($item['period_end'])->timezone('UTC')->toDateTimeString(),
                    "pv_estimate" => $item['pv_estimate'],
                ];
            })->toArray();
    }

    /**
     * Previous data can come from the Laravel log file, it is the raw JSON including the success message, not
     * the $response->json() used in getForecastData()
     *
     * @return mixed[]
     * @throws \JsonException
     */
    private function getPreviousData()
    {
        $rawData = '{"successful":true,"json":{"estimated_actuals":[{"pv_estimate":1.3873,"period_end":"2024-06-15T09:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.0472,"period_end":"2024-06-15T08:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.9639,"period_end":"2024-06-15T08:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3706,"period_end":"2024-06-15T07:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1097,"period_end":"2024-06-15T07:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.2536,"period_end":"2024-06-15T06:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.213,"period_end":"2024-06-15T06:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1075,"period_end":"2024-06-15T05:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0702,"period_end":"2024-06-15T05:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0206,"period_end":"2024-06-15T04:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T04:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T03:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T03:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T02:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T02:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T01:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T01:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T00:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T00:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T23:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T23:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T22:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T22:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T21:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T21:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0179,"period_end":"2024-06-14T20:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0885,"period_end":"2024-06-14T20:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1749,"period_end":"2024-06-14T19:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.274,"period_end":"2024-06-14T19:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.4177,"period_end":"2024-06-14T18:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3875,"period_end":"2024-06-14T18:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3399,"period_end":"2024-06-14T17:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3179,"period_end":"2024-06-14T17:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.1089,"period_end":"2024-06-14T16:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.4766,"period_end":"2024-06-14T16:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.4101,"period_end":"2024-06-14T15:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.3415,"period_end":"2024-06-14T15:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.7376,"period_end":"2024-06-14T14:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.2514,"period_end":"2024-06-14T14:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.5585,"period_end":"2024-06-14T13:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.4694,"period_end":"2024-06-14T13:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.348,"period_end":"2024-06-14T12:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.1976,"period_end":"2024-06-14T12:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.0217,"period_end":"2024-06-14T11:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.8241,"period_end":"2024-06-14T11:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.925,"period_end":"2024-06-14T10:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.8879,"period_end":"2024-06-14T10:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.3783,"period_end":"2024-06-14T09:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.3085,"period_end":"2024-06-14T09:00:00.0000000Z","period":"PT30M"}]}}';

        return collect(json_decode($rawData, true, 512, JSON_THROW_ON_ERROR)['json']['estimated_actuals'])
            ->map(function ($item) {
                return [
                    "period_end" => Carbon::parse($item['period_end'])->timezone('UTC')->toDateTimeString(),
                    "pv_estimate" => $item['pv_estimate'],
                ];
            })->toArray();
    }
}
