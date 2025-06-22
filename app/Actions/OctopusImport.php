<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OctopusImport
{
    /**
     * @throws \Throwable
     */
    public function run()
    {
        Log::info('Start running Octopus import action');

        $lastImportStart = \App\Models\OctopusImport::query()
            ->latest('interval_start')
            ->first('interval_start')
            ?->interval_start ?? now()->subDays(2);

        throw_if(
            $lastImportStart >= now()->subDay(),
            sprintf(
                'Last updated in the day, try again in %s',
                $lastImportStart->addDay()->diffForHumans()
            )
        );

        // fetch the latest import data
        $data = $this->getImportData();

        // save it to the database
        \App\Models\OctopusImport::upsert(
            $data,
            uniqueBy: ['interval_start'],
            update: ['consumption']
        );
    }

    /**
     * @throws \Throwable
     */
    private function getImportData()
    {
        $api = Config::get('octopus.api_key');
        $importMpan = Config::get('octopus.import_mpan');
        $importSerialNumber = Config::get('octopus.import_serial_number');

        $url = sprintf(
            // https://developer.octopus.energy/rest/guides/endpoints
            // ?page_size=200&period_from=2023-03-29T00:00Z&period_to=2023-03-29T01:29Z&order_by=period
            'https://api.octopus.energy/v1/electricity-meter-points/%s/meters/%s/consumption?page_size=200',
            $importMpan,
            $importSerialNumber,
        );

        try {
            $response = Http::withBasicAuth($api, '')->get($url);
        } catch (ConnectionException $e) {
            Log::error('There was a connection error trying to get Octopus import data:' . $e->getMessage());
            throw new \RuntimeException('There was a connection error trying to get Octopus import data:'
                . $e->getMessage());
        }

        $data = $response->json();
        Log::info(
            'Octopus import action',
            [
                'successful' => $response->successful(),
                'json' => $data,
            ]
        );

        throw_if($response->failed(), 'Unsuccessful Octopus import, check the log file for more details.');

        return collect($data['results'])
            ->map(function ($item) {
                return [
                    // "consumption":0.001,"interval_start":"2024-06-15T00:00:00+01:00","interval_end":"2024-06-15T00:30:00+01:00"
                    'consumption' => $item['consumption'],
                    'interval_start' => Carbon::parse($item['interval_start'])->timezone('UTC')->toDateTimeString(),
                    'interval_end' => Carbon::parse($item['interval_end'])->timezone('UTC')->toDateTimeString(),
                ];
            })->toArray();
    }
}
