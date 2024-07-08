<?php

declare(strict_types=1);


namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Forecast
{
    public function run()
    {
        Log::info('Start running Solcast forecast action');
        // check the last run (latest updated at), return if < 1 hour
        $lastForecast = \App\Models\Forecast::latest('updated_at')->first();

        throw_if(!empty($lastForecast) && $lastForecast->updated_at >= now()->subHour(),
            sprintf(
                'Last updated in the hour, try again in %s',
                $lastForecast->updated_at->addHour()->diffForHumans()
            )
        );

        // fetch the latest forecast data
        $data = $this->getForecastData();
        // $data = $this->getPreviousData();

        // save it to the database
        \App\Models\Forecast::upsert(
            $data,
            uniqueBy: ['period_end'],
            update: ['pv_estimate', 'pv_estimate10', 'pv_estimate90']
        );
    }

    /**
     * @throws \Throwable
     */
    private function getForecastData(): array
    {
        $api = Config::get('solcast.api_key');
        $resourceId = Config::get('solcast.resource_id');

        $url = sprintf(
            'https://api.solcast.com.au/rooftop_sites/%s/forecasts/?hours=72',
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
        Log::info('Solcast Forecast Action',
            [
                'successful' => $response->successful(),
                'json' => $data
            ]);

        throw_if($response->failed(), "Unsuccessful forecast, check the log file for more details.");

        return collect($data['forecasts'])
            ->map(function ($item) {
                return [
                    "period_end" => Carbon::parse($item['period_end'])->timezone('UTC')->toDateTimeString(),
                    "pv_estimate" => $item['pv_estimate'],
                    "pv_estimate10" => $item['pv_estimate10'],
                    "pv_estimate90" => $item['pv_estimate90'],
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
        $rawData = '{"successful":true,"json":{"forecasts":[{"pv_estimate":0.4606,"pv_estimate10":0.3711,"pv_estimate90":0.6451,"period_end":"2024-06-18T18:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.2366,"pv_estimate10":0.2008,"pv_estimate90":0.2684,"period_end":"2024-06-18T19:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1302,"pv_estimate10":0.1091,"pv_estimate90":0.1884,"period_end":"2024-06-18T19:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0865,"pv_estimate10":0.0692,"pv_estimate90":0.8261,"period_end":"2024-06-18T20:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0219,"pv_estimate10":0.0175,"pv_estimate90":0.0851,"period_end":"2024-06-18T20:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-18T21:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-18T21:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-18T22:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-18T22:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-18T23:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-18T23:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T00:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T00:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T01:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T01:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T02:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T02:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T03:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T03:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-19T04:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0238,"pv_estimate10":0.0175,"pv_estimate90":0.024990000000000002,"period_end":"2024-06-19T04:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0719,"pv_estimate10":0.068305,"pv_estimate90":0.075495,"period_end":"2024-06-19T05:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1176,"pv_estimate10":0.11171999999999999,"pv_estimate90":0.12348,"period_end":"2024-06-19T05:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1778,"pv_estimate10":0.16891,"pv_estimate90":0.18669000000000002,"period_end":"2024-06-19T06:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.2565,"pv_estimate10":0.243675,"pv_estimate90":0.26932500000000004,"period_end":"2024-06-19T06:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.4325,"pv_estimate10":0.410875,"pv_estimate90":0.454125,"period_end":"2024-06-19T07:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.5685,"pv_estimate10":0.540075,"pv_estimate90":0.596925,"period_end":"2024-06-19T07:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.7284,"pv_estimate10":0.69198,"pv_estimate90":0.76482,"period_end":"2024-06-19T08:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.9087,"pv_estimate10":0.863265,"pv_estimate90":0.954135,"period_end":"2024-06-19T08:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.0603,"pv_estimate10":1.007285,"pv_estimate90":1.113315,"period_end":"2024-06-19T09:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.2676,"pv_estimate10":1.2083,"pv_estimate90":1.3081,"period_end":"2024-06-19T09:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.438,"pv_estimate10":1.3103,"pv_estimate90":1.5252,"period_end":"2024-06-19T10:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.5857,"pv_estimate10":1.4109,"pv_estimate90":1.7186,"period_end":"2024-06-19T10:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.7491,"pv_estimate10":1.4977,"pv_estimate90":1.9457,"period_end":"2024-06-19T11:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.8598,"pv_estimate10":1.5518,"pv_estimate90":2.1215,"period_end":"2024-06-19T11:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.9694,"pv_estimate10":1.5773,"pv_estimate90":2.3079,"period_end":"2024-06-19T12:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.0305,"pv_estimate10":1.5611,"pv_estimate90":2.4525,"period_end":"2024-06-19T12:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.0497,"pv_estimate10":1.5051,"pv_estimate90":2.5671,"period_end":"2024-06-19T13:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.0574,"pv_estimate10":1.4222,"pv_estimate90":2.6673,"period_end":"2024-06-19T13:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.0389,"pv_estimate10":1.3132,"pv_estimate90":2.7349,"period_end":"2024-06-19T14:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.9773,"pv_estimate10":1.174,"pv_estimate90":2.7639,"period_end":"2024-06-19T14:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.8693,"pv_estimate10":1.0152,"pv_estimate90":2.7814,"period_end":"2024-06-19T15:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.7059,"pv_estimate10":0.841,"pv_estimate90":2.7479,"period_end":"2024-06-19T15:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.5,"pv_estimate10":0.6717,"pv_estimate90":2.6269,"period_end":"2024-06-19T16:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.284,"pv_estimate10":0.5334,"pv_estimate90":2.4611,"period_end":"2024-06-19T16:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.0814,"pv_estimate10":0.4329,"pv_estimate90":2.2586,"period_end":"2024-06-19T17:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.927,"pv_estimate10":0.3589,"pv_estimate90":2.077,"period_end":"2024-06-19T17:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.8243,"pv_estimate10":0.3041,"pv_estimate90":1.8809,"period_end":"2024-06-19T18:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.7379,"pv_estimate10":0.253,"pv_estimate90":1.718,"period_end":"2024-06-19T18:30:00.0000000Z","period":"PT30M"}]}}';

        return collect(json_decode($rawData, true, 512, JSON_THROW_ON_ERROR)['json']['forecasts'])
            ->map(function ($item) {
                return [
                    "period_end" => Carbon::parse($item['period_end'])->timezone('UTC')->toDateTimeString(),
                    "pv_estimate" => $item['pv_estimate'],
                    "pv_estimate10" => $item['pv_estimate10'],
                    "pv_estimate90" => $item['pv_estimate90'],
                ];
            })->toArray();
    }
}
