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

        $lastImport = \App\Models\OctopusImport::query()
            ->latest('interval_start')
            ->first('interval_start') ?? ['interval_start' => now()->subDays(2)];

        throw_if(!empty($lastImport) && $lastImport['interval_start'] >= now()->subDay(),
            sprintf(
                'Last updated in the day, try again in %s',
                $lastImport['interval_start']->addDay()->diffForHumans()
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
            'https://api.octopus.energy/v1/electricity-meter-points/%s/meters/%s/consumption/',
            $importMpan,
            $importSerialNumber,
        );

        try {
            $response = Http::withBasicAuth($api, '')->get($url);
        } catch (ConnectionException $e) {
            Log::error('There was a connection error trying to get Octopus import data:' . $e->getMessage());
            throw new \RuntimeException('There was a connection error trying to get Octopus import data:' . $e->getMessage());
        }

        $data = $response->json();
        Log::info('Octopus import action',
            [
                'successful' => $response->successful(),
                'json' => $data
            ]);

        throw_if($response->failed(), "Unsuccessful Octopus import, check the log file for more details.");

        return collect($data['results'])
            ->map(function ($item) {
                return [
                    // "consumption":0.001,"interval_start":"2024-06-15T00:00:00+01:00","interval_end":"2024-06-15T00:30:00+01:00"
                    "consumption" => $item['consumption'],
                    "interval_start" => Carbon::parse($item['interval_start'])->timezone('UTC')->toDateTimeString(),
                    "interval_end" => Carbon::parse($item['interval_end'])->timezone('UTC')->toDateTimeString(),
                ];
            })->toArray();
    }
}
