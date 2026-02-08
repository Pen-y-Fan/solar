<?php

namespace App\Domain\Solis\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

readonly class SolisInverterDayDataAction
{
    public function __construct(public string $date)
    {
    }

    public function execute(): array
    {
        Log::info('Starting Solis inverter day data action for date: ' . $this->date);

        $keyId = Config::get('solis.key_id');
        $keySecret = Config::get('solis.key_secret');
        $apiUrlBase = Config::get('solis.api_url');
        $inverterId = Config::get('solis.inverter_id');

        if (empty($keyId) || empty($keySecret) || empty($apiUrlBase) || empty($inverterId)) {
            throw new RuntimeException('Missing Solis configuration');
        }

        $bodyData = [
            'id' => $inverterId,
            'time' => $this->date,
            'timezone' => 0,
        ];
        $body = json_encode($bodyData);
        $contentMd5 = $this->getDigest($body);
        $dateHeader = $this->getGMTTime();
        $path = '/v1/api/inverterDay';
        $stringToSign = sprintf("POST\n%s\napplication/json\n%s\n%s", $contentMd5, $dateHeader, $path);
        $sign = $this->hmacSHA1($stringToSign, $keySecret);
        $url = rtrim($apiUrlBase, '/') . $path;

        $headers = [
            'Content-Type'  => 'application/json;charset=UTF-8',
            'Authorization' => sprintf("API %s:%s", $keyId, $sign),
            'Content-MD5'   => $contentMd5,
            'Date'          => $dateHeader,
        ];

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($url, $bodyData);

            Log::info('Solis inverter day data response', [
                'status' => $response->status(),
                'json'   => $response->json(),
            ]);

            $response->throw();
        } catch (ConnectionException | RequestException $e) {
            Log::warning('Solis API connection/request failed: ' . $e->getMessage());
            return [];
        }

        $json = $response->json();

        if (!($json['success'] ?? false)) {
            Log::warning('Solis API returned error: ' . ($json['msg'] ?? 'Unknown'));
            return [];
        }

        $apiData = $json['data'] ?? [];

        if (empty($apiData)) {
            Log::warning('No inverter data from Solis API');
            return [];
        }

        // Sort by timeStr
        usort($apiData, fn($a, $b) => strcmp($a['timeStr'] ?? '', $b['timeStr'] ?? ''));

        // Group into 30-minute buckets and keep only the first point for each bucket
        $buckets = [];
        foreach ($apiData as $point) {
            if (!isset($point['timeStr'])) {
                continue;
            }

            $ts = Carbon::parse($point['timeStr'])->utc()->floorMinutes(30);
            $periodStr = $ts->toDateTimeString();

            if (!isset($buckets[$periodStr])) {
                $buckets[$periodStr] = $point;
            }
        }

        $periods = array_keys($buckets);
        $records = [];
        $lastAbsolutePoint = end($apiData);

        for ($i = 0; $i < count($periods); $i++) {
            $currentPeriod = $periods[$i];
            $currentFirst = $buckets[$currentPeriod];

            // The 'end' point for this period is the 'first' point of the next period,
            // or the absolute last point of the day if it's the last bucket.
            $nextFirst = ($i + 1 < count($periods))
                ? $buckets[$periods[$i + 1]]
                : $lastAbsolutePoint;

            $records[] = [
                'period' => $currentPeriod,
                'yield' => max(0.0, round(
                    ($nextFirst['eToday'] ?? 0) - ($currentFirst['eToday'] ?? 0),
                    2
                )),
                'to_grid' => max(0.0, round(
                    ($nextFirst['gridSellTodayEnergy'] ?? 0) - ($currentFirst['gridSellTodayEnergy'] ?? 0),
                    2
                )),
                'from_grid' => max(0.0, round(
                    ($nextFirst['gridPurchasedTodayEnergy'] ?? 0) - ($currentFirst['gridPurchasedTodayEnergy'] ?? 0),
                    2
                )),
                'consumption' => max(0.0, round(
                    ($nextFirst['homeLoadTodayEnergy'] ?? 0) - ($currentFirst['homeLoadTodayEnergy'] ?? 0),
                    2
                )),
                'battery_soc' => (int) round(($nextFirst['batteryCapacitySoc'] ?? 0))
            ];
        }

        Log::info('Processed ' . count($records) . ' bucketed records from Solis API');

        return $records;
    }

    private function getDigest(string $data): string
    {
        return base64_encode(hash('md5', $data, true));
    }

    private function getGMTTime(): string
    {
        return gmdate('D, d M Y H:i:s \\G\\M\\T');
    }

    private function hmacSHA1(string $data, string $key): string
    {
        return base64_encode(hash_hmac('sha1', $data, $key, true));
    }
}
