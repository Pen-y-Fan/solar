<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgileExport
{
    /**
     * @throws \Throwable
     */
    public function run(): void
    {
        Log::info('Start running Agile export action');

        // normally released after 4PM and will have data up to 23:00 the next day!
        $lastExportValidTo = \App\Models\AgileExport::query()
            ->latest('valid_to')
            ->first('valid_to')
            ?->valid_to ?? now()->subDay();

        throw_if(now()->diffInUTCHours($lastExportValidTo) > 7,
            sprintf(
                'Already have data until %s, try again after 4 PM %s',
                $lastExportValidTo->timezone('Europe/London')->format('j F Y H:i'),
                $lastExportValidTo->timezone('Europe/London')->format('D')
            )
        );

        // fetch the latest export data
        $data = $this->getExportData($lastExportValidTo);

        // save it to the database
        \App\Models\AgileExport::upsert(
            $data,
            uniqueBy: ['valid_from'],
            update: ['value_exc_vat', 'value_inc_vat'],
        );
    }

    /**
     * @throws \Throwable
     */
    private function getExportData($lastExportValidTo): array
    {
        // https://developer.octopus.energy/rest/guides/endpoints
        // https://agile.octopushome.net/dashboard (watch network traffic and copy)
        $url = sprintf(
            'https://api.octopus.energy/v1/products/AGILE-OUTGOING-19-05-13/electricity-tariffs/E-1R-AGILE-OUTGOING-19-05-13-K/standard-unit-rates/?page_size=200&&period_from=%s&period_to=%s',
            $lastExportValidTo->clone()->timezone('Europe/London')->startOfDay()->timezone('UTC')->format('Y-m-d\TH:i\Z'),
            now('Europe/London')->addDay()->endOfDay()->timezone('UTC')->format('Y-m-d\TH:i\Z'),
        );

        try {
            $response = Http::get($url);
        } catch (ConnectionException $e) {
            Log::error('There was a connection error trying to get Agile export data:'.$e->getMessage());
            throw new \RuntimeException('There was a connection error trying to get Agile export data:'.$e->getMessage());
        }

        $data = $response->json();
        Log::info('Agile export action',
            [
                'successful' => $response->successful(),
                'json' => $data,
            ]);

        throw_if($response->failed(), 'Unsuccessful Agile export, check the log file for more details.');

        return collect($data['results'])
            ->map(function ($item) {
                return [
                    // {"value_exc_vat":18.04,"value_inc_vat":18.942,"valid_from":"2024-06-20T21:30:00Z","valid_to":"2024-06-20T22:00:00Z","payment_method":null}
                    'value_exc_vat' => $item['value_exc_vat'],
                    'value_inc_vat' => $item['value_inc_vat'],
                    'valid_from' => Carbon::parse($item['valid_from'])->timezone('UTC')->toDateTimeString(),
                    'valid_to' => Carbon::parse($item['valid_to'])->timezone('UTC')->toDateTimeString(),
                ];
            })->toArray();
    }
}
