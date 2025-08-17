<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Actions;

use App\Domain\Forecasting\ValueObjects\PvEstimate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Domain\Forecasting\Models\Forecast;

class ForecastAction
{
    public function run()
    {
        Log::info('Start running Solcast forecast action');
        // check the last run (latest updated at), return if < 1 hour
        $lastForecast = Forecast::latest('updated_at')->first();

        throw_if(
            ! empty($lastForecast) && $lastForecast->updated_at >= now()->subHour(),
            sprintf(
                'Last updated in the hour, try again in %s',
                $lastForecast->updated_at->addHour()->diffForHumans()
            )
        );

        // fetch the latest forecast data
        $data = $this->getForecastData();
        // $data = $this->getPreviousData();

        // save it to the database
        Forecast::upsert(
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
            'Solcast Forecast Action',
            [
                'successful' => $response->successful(),
                'json' => $data,
            ]
        );

        throw_if($response->failed(), 'Unsuccessful forecast, check the log file for more details.');

        return collect($data['forecasts'])
            ->map(function ($item) {
                // Create a PvEstimate value object
                $pvEstimate = new PvEstimate(
                    estimate: $item['pv_estimate'],
                    estimate10: $item['pv_estimate10'],
                    estimate90: $item['pv_estimate90']
                );

                // Convert to array and add period_end
                return array_merge(
                    ['period_end' => Carbon::parse($item['period_end'])->timezone('UTC')->toDateTimeString()],
                    $pvEstimate->toArray()
                );
            })->toArray();
    }
}
