<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Actions;

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
        try {
            Log::info('Start running Solcast actual forecast action');

            $lastForecast = ActualForecast::latest('updated_at')
                ->first('updated_at');

            throw_if(
                !empty($lastForecast) && $lastForecast->updated_at >= now()->subHour(),
                sprintf(
                    'Last updated in the hour, try again in %s',
                    $lastForecast->updated_at->addHour()->diffForHumans()
                )
            );

            $data = $this->getForecastData();

            ActualForecast::upsert(
                $data,
                uniqueBy: ['period_end'],
                update: ['pv_estimate']
            );

            return ActionResult::success(['records' => count($data)], 'Actual forecast updated');
        } catch (\Throwable $e) {
            Log::warning('ActualForecastAction failed', ['exception' => $e->getMessage()]);
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
            throw new \RuntimeException('There was a connection error trying to get Solcast forecast data:'
                . $e->getMessage());
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
                $errorMessage = 'Solcast API rate limit exceeded. You have made too many requests today. ' .
                    'Please wait until tomorrow or reduce the frequency of actual forecast updates.';

                $lastActualForecast = ActualForecast::latest('updated_at')->first('updated_at');
                assert($lastActualForecast instanceof ActualForecast);

                Log::error('Solcast Rate Limit Exceeded', [
                    'status_code'          => $response->status(),
                    'message'              => 'Daily API limit reached',
                    'last_forecast_update' => $lastActualForecast->updated_at?->toDateTimeString(),
                    'url'                  => $url,
                ]);

                // Update the last forecast update time to force a backoff
                $lastActualForecast->updated_at = now();
                $lastActualForecast->save();

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
        if (!is_array($data) || !isset($data['estimated_actuals'])) {
            throw new \RuntimeException(
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
}
