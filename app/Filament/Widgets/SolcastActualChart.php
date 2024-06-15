<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class SolcastActualChart extends ChartWidget
{
    protected static ?string $heading = 'Solis Actual';

    protected function getData(): array
    {
        $rawData = collect($this->staticData());

        $label = sprintf('actual from %s to %s',
            Carbon::parse($rawData->first()['period_end'], 'UTC')
                ->timezone(config('app.timezone'))
                ->format('d M H:i'),
            Carbon::parse($rawData->last()['period_end'], 'UTC')
                ->timezone(config('app.timezone'))
                ->format('d M Y H:i')
        );

        self::$heading = 'Solis ' . $label;

        $label = str($label)->ucfirst();

        return [
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $rawData->map(function ($item) {
                        return $item['pv_estimate'];
                    }),
                ],
            ],
            'labels' => $rawData->map(function ($item) {
                return Carbon::parse($item['period_end'], 'UTC')
                    ->timezone(config('app.timezone'))
                    ->format('H:i');
            }),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /*
     * Will need to be an action, with cache and save to database. Hobbyist API calls are restricted to 10 per day!
     */
    private function getActualData()
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
            dump('there was a connection error');
            exit();
        }

        $data = $response->json();
        Log::info('Solcast actual',
            [
                'successful' => $response->successful(),
                'json' => $data
            ]);


        return $data;
    }

    /**
     * This static data will be replaced with a database query
     * @return array
     */

    private function staticData(): array
    {
        return json_decode('{"estimated_actuals":[{"pv_estimate":1.3873,"period_end":"2024-06-15T09:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.0472,"period_end":"2024-06-15T08:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.9639,"period_end":"2024-06-15T08:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3706,"period_end":"2024-06-15T07:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1097,"period_end":"2024-06-15T07:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.2536,"period_end":"2024-06-15T06:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.213,"period_end":"2024-06-15T06:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1075,"period_end":"2024-06-15T05:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0702,"period_end":"2024-06-15T05:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0206,"period_end":"2024-06-15T04:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T04:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T03:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T03:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T02:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T02:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T01:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T01:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T00:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-15T00:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T23:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T23:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T22:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T22:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T21:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0,"period_end":"2024-06-14T21:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0179,"period_end":"2024-06-14T20:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.0885,"period_end":"2024-06-14T20:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.1749,"period_end":"2024-06-14T19:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.274,"period_end":"2024-06-14T19:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.4177,"period_end":"2024-06-14T18:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3875,"period_end":"2024-06-14T18:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3399,"period_end":"2024-06-14T17:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.3179,"period_end":"2024-06-14T17:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.1089,"period_end":"2024-06-14T16:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.4766,"period_end":"2024-06-14T16:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.4101,"period_end":"2024-06-14T15:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.3415,"period_end":"2024-06-14T15:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.7376,"period_end":"2024-06-14T14:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.2514,"period_end":"2024-06-14T14:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.5585,"period_end":"2024-06-14T13:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.4694,"period_end":"2024-06-14T13:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.348,"period_end":"2024-06-14T12:30:00.0000000Z","period":"PT30M"},{"pv_estimate":2.1976,"period_end":"2024-06-14T12:00:00.0000000Z","period":"PT30M"},{"pv_estimate":2.0217,"period_end":"2024-06-14T11:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.8241,"period_end":"2024-06-14T11:00:00.0000000Z","period":"PT30M"},{"pv_estimate":0.925,"period_end":"2024-06-14T10:30:00.0000000Z","period":"PT30M"},{"pv_estimate":0.8879,"period_end":"2024-06-14T10:00:00.0000000Z","period":"PT30M"},{"pv_estimate":1.3783,"period_end":"2024-06-14T09:30:00.0000000Z","period":"PT30M"},{"pv_estimate":1.3085,"period_end":"2024-06-14T09:00:00.0000000Z","period":"PT30M"}]}', true)['estimated_actuals'];
    }
}
