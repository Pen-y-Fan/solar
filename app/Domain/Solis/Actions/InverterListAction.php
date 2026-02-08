<?php

declare(strict_types=1);

namespace App\Domain\Solis\Actions;

use App\Support\Actions\ActionResult;
use App\Support\Actions\Contracts\ActionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InverterListAction implements ActionInterface
{
    public function execute(): ActionResult
    {
        Log::info('Starting Solis inverter list action');

        $keyId = Config::get('solis.key_id');
        $keySecret = Config::get('solis.key_secret');
        $apiUrlBase = Config::get('solis.api_url');

        if (empty($keyId) || empty($keySecret) || empty($apiUrlBase)) {
            return ActionResult::failure('Missing Solis configuration');
        }

        $bodyData = [
            'pageNo'   => 1,
            'pageSize' => 1,
        ];
        $body = json_encode($bodyData);
        $contentMd5 = $this->getDigest($body);
        $date = $this->getGMTTime();
        $path = '/v1/api/inverterList';
        $stringToSign = "POST\n{$contentMd5}\napplication/json\n{$date}\n{$path}";
        $sign = $this->hmacSHA1($stringToSign, $keySecret);
        $url = rtrim($apiUrlBase, '/') . $path;

        $headers = [
            'Content-Type'  => 'application/json;charset=UTF-8',
            'Authorization' => "API {$keyId}:{$sign}",
            'Content-MD5'   => $contentMd5,
            'Date'          => $date,
        ];

        $response = Http::withHeaders($headers)
            ->post($url, $bodyData);

        $body = $response->json();
        Log::info('Solis inverter list response', [
            'status' => $response->status(),
            'json'   => $body, // Decoded body
        ]);

        if ($response->successful()) {
            return ActionResult::success(
                [
                    'status'     => $response->status(),
                    'inverterId' => $body['data']['page']['records'][0]['inverterId'] ?? '',
                ],
                'Solis inverter list fetched successfully'
            );
        } else {
            return ActionResult::failure(sprintf(
                "Solis API failed: %s - %s",
                $response->status(),
                $response->body()
            ));
        }
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
