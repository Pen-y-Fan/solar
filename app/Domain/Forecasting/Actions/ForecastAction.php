<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Actions;

use App\Domain\Forecasting\ValueObjects\PvEstimate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Domain\Forecasting\Models\Forecast;
use App\Support\Actions\ActionResult;
use App\Support\Actions\Contracts\ActionInterface;

class ForecastAction implements ActionInterface
{
    public function execute(): ActionResult
    {
        try {
            Log::info('Start running Solcast forecast action');
            // check the last run (latest updated at), return if < 4 hours to reduce API calls
            $lastForecast = Forecast::latest('updated_at')
                ->first('updated_at');

            throw_if(
                !empty($lastForecast) && $lastForecast->updated_at >= now()->subHours(4),
                sprintf(
                    'Last updated within 4 hours, try again in %s to avoid rate limiting',
                    $lastForecast->updated_at->addHours(4)->diffForHumans()
                )
            );

            // fetch the latest forecast data
            $data = $this->getForecastData();
            // $data = $this->getPreviousData();

            // save it to the database
            Forecast::upsert(
                $data,
                uniqueBy: ['period_end'],
                update: ['pv_estimate', 'pv_estimate10', 'pv_estimate90']
            );

            return ActionResult::success(['records' => count($data)], 'Forecast updated');
        } catch (\Throwable $e) {
            Log::warning('ForecastAction failed', ['exception' => $e->getMessage()]);
            return ActionResult::failure($e->getMessage());
        }
    }

    /**
     * @throws \Throwable
     */
    private function getForecastData(): array
    {
        $api = Config::get('solcast.api_key');
        $resourceId = Config::get('solcast.resource_id');

        // Validate configuration
        throw_if(empty($api), 'Solcast API key is not configured. Please set SOLCAST_API_KEY in your environment.');
        throw_if(
            empty($resourceId),
            'Solcast resource ID is not configured. Please set SOLCAST_RESOURCE_ID in your environment.'
        );

        Log::info('Solcast Configuration', [
            'api_key_length'  => strlen($api),
            'resource_id'     => $resourceId,
        ]);

        $url = sprintf(
            'https://api.solcast.com.au/rooftop_sites/%s/forecasts/?hours=72',
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
            throw new \RuntimeException('There was a connection error trying to get Solcast forecast data:'
                . $e->getMessage());
        }

        $data = $response->json();

        // Enhanced logging with more details
        Log::info(
            'Solcast Forecast Action v2',
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
                $errorMessage = 'Solcast API rate limit exceeded. You have made too many requests today. ' .
                    'Please wait until tomorrow or reduce the frequency of forecast updates.';

                $lastForecast = Forecast::latest('updated_at')->first();
                assert($lastForecast instanceof Forecast);

                Log::error('Solcast Rate Limit Exceeded', [
                    'status_code'          => $response->status(),
                    'message'              => 'Daily API limit reached',
                    'last_forecast_update' => $lastForecast->updated_at?->toDateTimeString(),
                    'url'                  => $url,
                ]);

                $lastForecast->updated_at = now(); // Update the last forecast update time to force a backoff
                $lastForecast->save();

                throw new \RuntimeException($errorMessage);
            }

            $errorMessage = sprintf(
                'Solcast API request failed with status %d. Response: %s',
                $response->status(),
                $response->body()
            );

            Log::error('Solcast API Error', [
                'status_code'   => $response->status(),
                'response_body' => $response->body(),
                'url'           => $url,
                'headers_sent'  => $headers,
            ]);

            throw new \RuntimeException($errorMessage);
        }

        // Validate response structure
        if (!is_array($data) || !isset($data['forecasts'])) {
            throw new \RuntimeException(
                'Invalid response structure from Solcast API. Expected "forecasts" key in response.'
            );
        }

        if (empty($data['forecasts'])) {
            Log::warning('Solcast API returned empty forecasts array');
            return [];
        }

        return collect($data['forecasts'])
            ->map(function ($item) {
                // Validate required fields
                $requiredFields = ['pv_estimate', 'pv_estimate10', 'pv_estimate90', 'period_end'];
                foreach ($requiredFields as $field) {
                    if (!isset($item[$field])) {
                        throw new \RuntimeException("Missing required field '{$field}' in forecast data");
                    }
                }

                // Create a PvEstimate value object
                $pvEstimate = new PvEstimate(
                    estimate: $item['pv_estimate'],
                    estimate10: $item['pv_estimate10'],
                    estimate90: $item['pv_estimate90']
                );

                // Convert to array and add period_end
                return array_merge(
                    ['period_end' => Carbon::parse($item['period_end'])->timezone('UTC')->toDateTimeString()],
                    $pvEstimate->toArray()
                );
            })->toArray();
    }
}
