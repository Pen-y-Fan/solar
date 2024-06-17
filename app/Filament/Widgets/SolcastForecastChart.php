<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolcastForecastChart extends ChartWidget
{
    protected static ?string $heading = 'Solis Forecast';

    protected function getData(): array
    {
        $rawData = collect($this->staticData());
        // $rawData = collect($this->getForecastData()['forecasts']);

        self::$heading = sprintf('Solis forecast from %s to %s',
            Carbon::parse($rawData->first()['period_end'], 'UTC')
                ->timezone(config('app.timezone'))
                ->format('d M H:i'),
            Carbon::parse($rawData->last()['period_end'], 'UTC')
                ->timezone(config('app.timezone'))
                ->format('d M Y H:i')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Forecast (10%)',
                    'data' => $rawData->map(fn($item): string => $item['pv_estimate10']),
                    'fill' => "+1",
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)'
                ],
                [
                    'label' => 'Forecast',
                    'data' => $rawData->map(fn($item): string => $item['pv_estimate']),
                    'fill' => "+1",
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgb(255, 99, 132)'
                ],
                [
                    'label' => 'Forecast (90%)',
                    'data' => $rawData->map(fn($item): string => $item['pv_estimate90']),
                ],
            ],
            'labels' => $rawData->map(fn($item): string => Carbon::parse($item['period_end'], 'UTC')
                ->timezone(config('app.timezone'))
                ->format('H:i')),
        ];
    }
    /*
        backgroundColor: [
          'rgba(255, 99, 132, 0.2)',
          'rgba(255, 159, 64, 0.2)',
          'rgba(255, 205, 86, 0.2)',
          'rgba(75, 192, 192, 0.2)',
          'rgba(54, 162, 235, 0.2)',
          'rgba(153, 102, 255, 0.2)',
          'rgba(201, 203, 207, 0.2)'
        ],
        borderColor: [
          'rgb(255, 99, 132)',
          'rgb(255, 159, 64)',
          'rgb(255, 205, 86)',
          'rgb(75, 192, 192)',
          'rgb(54, 162, 235)',
          'rgb(153, 102, 255)',
          'rgb(201, 203, 207)'
        ],
     */

    /**
    * Will need to be an action, with cache and save to database. Hobbyist API calls are restricted to 10 per day!
    */
    private function getForecastData()
    {
        $api = Config::get('solcast.api_key');
        $resourceId = Config::get('solcast.resource_id');

        $url = sprintf(
            'https://api.solcast.com.au/rooftop_sites/%s/forecasts/?hours=48',
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
            dump('there was a connection error');
            exit();
        }

        $data = $response->json();
        Log::info('Solcast forecast',
            [
                'successful' => $response->successful(),
                'json' => $data
            ]);


        return $data;
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * This static data will be replaced with a database query
     * @return array
     */
    private function staticData(): array
    {
        return json_decode('{"forecasts":[{"pv_estimate":1.2633,"pv_estimate10":0.9354,"pv_estimate90":1.4486,"period_end":"2024-06-15T09:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.6483,"pv_estimate10":1.2923,"pv_estimate90":1.7307150000000002,"period_end":"2024-06-15T10:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.7032,"pv_estimate10":0.3954,"pv_estimate90":1.7112,"period_end":"2024-06-15T10:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.8694,"pv_estimate10":1.5959,"pv_estimate90":1.9891,"period_end":"2024-06-15T11:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.1221,"pv_estimate10":1.9696,"pv_estimate90":2.1564,"period_end":"2024-06-15T11:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.1506,"pv_estimate10":1.8567,"pv_estimate90":2.3331,"period_end":"2024-06-15T12:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.1249,"pv_estimate10":1.4109,"pv_estimate90":2.4687,"period_end":"2024-06-15T12:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.9704,"pv_estimate10":0.7966,"pv_estimate90":2.6174,"period_end":"2024-06-15T13:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.8711,"pv_estimate10":0.5965,"pv_estimate90":2.7112,"period_end":"2024-06-15T13:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.7618,"pv_estimate10":0.4913,"pv_estimate90":2.7813,"period_end":"2024-06-15T14:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.5895,"pv_estimate10":0.3694,"pv_estimate90":2.8281,"period_end":"2024-06-15T14:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.3454,"pv_estimate10":0.2441,"pv_estimate90":2.8306,"period_end":"2024-06-15T15:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.1097,"pv_estimate10":0.1557,"pv_estimate90":2.7522,"period_end":"2024-06-15T15:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.9423,"pv_estimate10":0.1138,"pv_estimate90":2.6175,"period_end":"2024-06-15T16:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.7814,"pv_estimate10":0.0919,"pv_estimate90":2.4347,"period_end":"2024-06-15T16:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.6458,"pv_estimate10":0.0788,"pv_estimate90":2.1565,"period_end":"2024-06-15T17:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.481,"pv_estimate10":0.0591,"pv_estimate90":1.7821,"period_end":"2024-06-15T17:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3181,"pv_estimate10":0.035,"pv_estimate90":1.3019,"period_end":"2024-06-15T18:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.2177,"pv_estimate10":0.0219,"pv_estimate90":0.9864,"period_end":"2024-06-15T18:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.145,"pv_estimate10":0.0153,"pv_estimate90":0.7238,"period_end":"2024-06-15T19:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0897,"pv_estimate10":0.0109,"pv_estimate90":0.4653,"period_end":"2024-06-15T19:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0481,"pv_estimate10":0.0044,"pv_estimate90":0.1468,"period_end":"2024-06-15T20:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0109,"pv_estimate10":0.0022,"pv_estimate90":0.0197,"period_end":"2024-06-15T20:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-15T21:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-15T21:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-15T22:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-15T22:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-15T23:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-15T23:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T00:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T00:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T01:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T01:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T02:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T02:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T03:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T03:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"pv_estimate10":0,"pv_estimate90":0,"period_end":"2024-06-16T04:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0249,"pv_estimate10":0.0136,"pv_estimate90":0.026144999999999998,"period_end":"2024-06-16T04:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0798,"pv_estimate10":0.0537,"pv_estimate90":0.08379,"period_end":"2024-06-16T05:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.126,"pv_estimate10":0.1097,"pv_estimate90":0.1323,"period_end":"2024-06-16T05:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1747,"pv_estimate10":0.16596499999999997,"pv_estimate90":0.18343500000000001,"period_end":"2024-06-16T06:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.2145,"pv_estimate10":0.20377499999999998,"pv_estimate90":0.225225,"period_end":"2024-06-16T06:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3327,"pv_estimate10":0.316065,"pv_estimate90":0.349335,"period_end":"2024-06-16T07:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.4616,"pv_estimate10":0.43851999999999997,"pv_estimate90":0.48468000000000006,"period_end":"2024-06-16T07:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.6513,"pv_estimate10":0.5645,"pv_estimate90":0.6838650000000001,"period_end":"2024-06-16T08:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.8681,"pv_estimate10":0.623,"pv_estimate90":0.911505,"period_end":"2024-06-16T08:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.0473,"pv_estimate10":0.6816,"pv_estimate90":1.0545,"period_end":"2024-06-16T09:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.275,"pv_estimate10":0.7277,"pv_estimate90":1.3164,"period_end":"2024-06-16T09:30:00.0000000Z","period":"PT30M"}]}', true)['forecasts'];
    }
}
