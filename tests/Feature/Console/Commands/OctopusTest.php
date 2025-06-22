<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\Octopus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OctopusTest extends TestCase
{
    use RefreshDatabase;

    public function testOctopusConsoleCommand(): void
    {
        // Arrange
        Http::fake([
            // Mock for OctopusImport & Export HTTP calls
            'https://api.octopus.energy/v1/electricity-meter-points/*/meters/*/consumption*' => Http::response([
                'results' => [
                    [
                        'consumption' => 0.001,
                        'interval_start' => '2024-06-15T00:00:00+01:00',
                        'interval_end' => '2024-06-15T00:30:00+01:00',
                    ],
                ],
            ], 200),

            // Mock for AgileImport & Export HTTP calls
            'https://api.octopus.energy/v1/products/*/electricity-tariffs/*/standard-unit-rates/*' => Http::response([
                'results' => [
                    [
                        'value_exc_vat' => 18.04,
                        'value_inc_vat' => 18.94,
                        'valid_from' => '2024-06-20T21:30:00Z',
                        'valid_to' => '2024-06-20T22:00:00Z',
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')->atLeast()->once();

        // Act and Assert
        $this->artisan((new Octopus())->getName())
            ->expectsOutputToContain('Running Octopus action!')
            ->expectsOutputToContain('Octopus import has been fetched!')
            ->expectsOutputToContain('Octopus export has been fetched!')
            ->expectsOutputToContain('Octopus Agile import has been fetched!')
            ->expectsOutputToContain('Octopus Agile export has been fetched!')
            ->assertSuccessful();

        Http::assertSentCount(4);
    }
}
