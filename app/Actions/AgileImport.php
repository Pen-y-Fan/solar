<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgileImport
{
    /**
     * @throws \Throwable
     */
    public function run(): void
    {
        Log::info('Start running Agile import action');

        // normally released after 4PM and will have data up to 23:00 the next day!
        $lastImport = \App\Models\AgileImport::query()
            ->latest('valid_to')
            ->first('valid_to') ?? ['valid_to' => now()->subDay()];

        throw_if(now()->diffInUTCHours($lastImport['valid_to']) > 7,
            sprintf(
                'Already have data until %s, try again after 4 PM %s',
                $lastImport['valid_to']->timezone('Europe/London')->format('j F Y H:i'),
                $lastImport['valid_to']->timezone('Europe/London')->format('D')
            )
        );

        // fetch the latest import data
        $data = $this->getImportData($lastImport);

        // save it to the database
        \App\Models\AgileImport::upsert(
            $data,
            uniqueBy: ['valid_from'],
            update: ['value_exc_vat', 'value_inc_vat'],
        );
    }

    /**
     * @throws \Throwable
     */
    private function getImportData($lastImport): array
    {
        // https://developer.octopus.energy/rest/guides/endpoints
        // https://agile.octopushome.net/dashboard (watch network traffic and copy)
        $url = sprintf(
            'https://api.octopus.energy/v1/products/AGILE-24-10-01/electricity-tariffs/E-1R-AGILE-24-10-01-K/standard-unit-rates/?page_size=200&&period_from=%s&period_to=%s',
            $lastImport->clone()->timezone('Europe/London')->startOfDay()->timezone('UTC')->format('Y-m-d\TH:i\Z'),
            now('Europe/London')->addDay()->endOfDay()->timezone('UTC')->format('Y-m-d\TH:i\Z'),
        );

        try {
            $response = Http::get($url);
        } catch (ConnectionException $e) {
            Log::error('There was a connection error trying to get Agile import data:' . $e->getMessage());
            throw new \RuntimeException('There was a connection error trying to get Agile import data:' . $e->getMessage());
        }

        $data = $response->json();
        Log::info('Agile import action',
            [
                'successful' => $response->successful(),
                'json' => $data
            ]);

        throw_if($response->failed(), "Unsuccessful Agile import, check the log file for more details.");

        return collect($data['results'])
            ->map(function ($item) {
                return [
                    // {"value_exc_vat":18.04,"value_inc_vat":18.942,"valid_from":"2024-06-20T21:30:00Z","valid_to":"2024-06-20T22:00:00Z","payment_method":null}
                    "value_exc_vat" => $item['value_exc_vat'],
                    "value_inc_vat" => $item['value_inc_vat'],
                    "valid_from" => Carbon::parse($item['valid_from'])->timezone('UTC')->toDateTimeString(),
                    "valid_to" => Carbon::parse($item['valid_to'])->timezone('UTC')->toDateTimeString(),
                ];
            })->toArray();
    }
}
