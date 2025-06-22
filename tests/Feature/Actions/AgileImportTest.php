<?php

namespace Tests\Feature\Actions;

use App\Domain\Energy\Actions\AgileImport;
use App\Domain\Energy\Models\AgileImport as AgileImportModel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AgileImportTest extends TestCase
{
    use DatabaseMigrations;

    public function testAgileImportRunSuccess(): void
    {
        // Arrange
        $start = Carbon::parse('2024-06-15 00:00:00')->timezone('UTC');
        $end = Carbon::parse('2024-06-15 00:30:00')->timezone('UTC');
        $valueExcVat = 18.04;
        $valueIncVat = 18.94;

        Http::fake([
            'https://api.octopus.energy/*' => Http::response([
                'results' => [
                    [
                        'value_exc_vat' => $valueExcVat,
                        'value_inc_vat' => $valueIncVat,
                        'valid_from' => $start->toISOString(),
                        'valid_to' => $end->toISOString(),
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        $agileImport = new AgileImport();

        // Act
        $agileImport->run();

        // Assertions
        $this->assertDatabaseCount(AgileImportModel::class, 1);
        $result = AgileImportModel::first();

        $this->assertSame($valueExcVat, $result->value_exc_vat);
        $this->assertSame($valueIncVat, $result->value_inc_vat);
        $this->assertSame($start->toDateTimeString(), $result->valid_from->toDateTimeString());
        $this->assertSame($end->toDateTimeString(), $result->valid_to->toDateTimeString());
    }
}
