<?php

declare(strict_types=1);

namespace App\Domain\Energy\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Account
{
    /**
     * @throws \Throwable
     */
    public function run()
    {
        Log::info('Start running Octopus account action');

        $this->getAccountData();
    }

    /**
     * @throws \Throwable
     */
    private function getAccountData()
    {
        $api = Config::get('octopus.api_key');
        $account = Config::get('octopus.account');

        $url = sprintf(
            // https://developer.octopus.energy/rest/guides/endpoints
            // https://api.octopus.energy/v1/accounts/<account-number>/
            // The account endpoint is available to all customers, but you will need to authenticate with your API key.
            'https://api.octopus.energy/v1/accounts/%s/',
            $account
        );

        try {
            $response = Http::withBasicAuth($api, '')->get($url);
        } catch (ConnectionException $e) {
            Log::error('There was a connection error trying to get Octopus account data:' . $e->getMessage());
            throw new \RuntimeException('There was a connection error trying to get Octopus account data:'
                . $e->getMessage());
        }

        $data = $response->json();
        Log::info(
            'Octopus account action',
            [
                'successful' => $response->successful(),
                'json' => $data,
            ]
        );

        throw_if($response->failed(), 'Unsuccessful Octopus account, check the log file for more details.');
    }
}
