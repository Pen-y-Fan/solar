<?php

namespace Tests\Feature\Actions;

use App\Domain\Energy\Actions\OctopusExport;
use App\Domain\Energy\Models\OctopusExport as OctopusExportModel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OctopusExportTest extends TestCase
{
    use DatabaseMigrations;

    public function testOctopusExportRunSuccess()
    {
        // Arrange
        $start = Carbon::parse('2024-06-15 00:00:00')->timezone('UTC');
        $end = Carbon::parse('2024-06-15 00:30:00')->timezone('UTC');
        Http::fake([
            'https://api.octopus.energy/*' => Http::response([
                'results' => [
                    [
                        'consumption' => 0.001,
                        'interval_start' => $start->toISOString(),
                        'interval_end' => $end->toISOString(),
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        $octopusExport = new OctopusExport();

        // Act
        $octopusExport->run();

        // Assertions
        $this->assertDatabaseCount(OctopusExportModel::class, 1);
        $result = OctopusExportModel::first();

        $this->assertSame(0.001, $result->consumption);
        $this->assertSame($start->toDateTimeString(), $result->interval_start->toDateTimeString());
        $this->assertSame($end->toDateTimeString(), $result->interval_end->toDateTimeString());
    }
}
