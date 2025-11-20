<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Actions;

use App\Domain\Forecasting\Exceptions\ClientErrorException;
use App\Domain\Forecasting\Exceptions\MissingApiKeyException;
use App\Domain\Forecasting\Exceptions\RateLimitedException;
use App\Domain\Forecasting\Exceptions\ServerErrorException;
use App\Domain\Forecasting\Exceptions\TransportException;
use App\Domain\Forecasting\Exceptions\UnexpectedResponseException;
use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\Forecasting\ValueObjects\PvEstimate;
use App\Support\Actions\ActionResult;
use App\Support\Actions\Contracts\ActionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActualForecastAction implements ActionInterface
{
    public function execute(): ActionResult
    {
        Log::info('Start running Solcast actual forecast action');

        $data = $this->getForecastData();

        ActualForecast::upsert(
            $data,
            uniqueBy: ['period_end'],
            update: ['pv_estimate']
        );

        return ActionResult::success(['records' => count($data)], 'Actual forecast updated');
    }

    /**
     * @throws \Throwable
     */
    private function getForecastData(): array
    {
        $api = $this->getApiKeyOrFail();
        $resourceId = $this->getResourceOrFail();

        $url = sprintf(
            'https://api.solcast.com.au/rooftop_sites/%s/estimated_actuals?format=json&hours=72',
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
            throw new TransportException(
                'There was a connection error trying to get Solcast forecast data: ' . $e->getMessage()
            );
        }

        $data = $response->json();

        // Enhanced logging with more details
        Log::info(
            'Solcast Actual Forecast Action detail',
            [
                'successful'  => $response->successful(),
                'status_code' => $response->status(),
                'headers'     => $response->headers(),
                'json'        => $data,
                'body'        => $response->body(),
                'url'         => $url,
            ]
        );

        if ($response->failed()) {
            // Handle rate limiting specifically
            if ($response->status() === 429) {
                Log::error('Solcast Rate Limit Exceeded', [
                    'status_code' => $response->status(),
                    'message'     => 'Daily API limit reached',
                    'url'         => $url,
                ]);
                throw new RateLimitedException(
                    'Solcast API rate limit exceeded. You have made too many requests today.'
                );
            }

            $status = $response->status();
            Log::error('Solcast API Error', [
                'status_code'   => $status,
                'response_body' => $response->body(),
                'url'           => $url,
                'headers_sent'  => $headers,
            ]);

            if ($status >= 500) {
                throw new ServerErrorException(
                    sprintf('Solcast API request failed with status %d.', $status),
                    $status
                );
            }

            throw new ClientErrorException(
                sprintf('Solcast API request failed with status %d.', $status),
                $status
            );
        }

        // Validate response structure
        if (!is_array($data) || !isset($data['estimated_actuals'])) {
            throw new UnexpectedResponseException(
                'Invalid response structure from Solcast API. Expected "estimated_actuals" key in response.'
            );
        }

        if (empty($data['estimated_actuals'])) {
            Log::warning('Solcast API returned empty forecasts array');
            return [];
        }
        return collect($data['estimated_actuals'])
            ->map(function ($item) {
                // Create a PvEstimate value object with only the main estimate
                $pvEstimate = PvEstimate::fromSingleEstimate($item['pv_estimate']);

                // Convert to array with only the main estimate and add period_end
                return array_merge(
                    ['period_end' => Carbon::parse($item['period_end'])->timezone('UTC')->toDateTimeString()],
                    $pvEstimate->toSingleArray()
                );
            })->toArray();
    }

    private function getApiKeyOrFail(): string
    {
        $api = (string)Config::get('solcast.api_key');

        if (empty($api)) {
            throw new MissingApiKeyException(
                'Solcast API key is not configured. Please set SOLCAST_API_KEY in your environment.'
            );
        }
        return $api;
    }

    private function getResourceOrFail(): string
    {
        $resourceId = Config::get('solcast.resource_id');

        if (empty($resourceId)) {
            throw new ClientErrorException(
                'Solcast resource ID is not configured. Please set SOLCAST_RESOURCE_ID in your environment.',
                400
            );
        }
        return $resourceId;
    }
}
