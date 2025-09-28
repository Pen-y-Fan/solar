<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Queries;

use App\Application\Queries\Energy\AgileImportExportSeriesQuery;
use App\Domain\Energy\Models\AgileImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AgileImportExportSeriesQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsShapedSeriesLimitedByCount(): void
    {
        // Arrange: create 3 import rows with ascending valid_from
        AgileImport::factory()->create([
            'valid_from' => '2025-01-01 00:00:00',
            'valid_to' => '2025-01-01 00:30:00',
            'value_inc_vat' => 10.5,
        ]);
        AgileImport::factory()->create([
            'valid_from' => '2025-01-01 00:30:00',
            'valid_to' => '2025-01-01 01:00:00',
            'value_inc_vat' => 11.0,
        ]);
        AgileImport::factory()->create([
            'valid_from' => '2025-01-01 01:00:00',
            'valid_to' => '2025-01-01 01:30:00',
            'value_inc_vat' => 12.25,
        ]);

        $query = new AgileImportExportSeriesQuery();

        // Act: request starting from first with a limit of 2
        $series = $query->run(new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC')), 2);

        // Assert: we got 2 items, shaped with the expected keys and numeric values
        $this->assertCount(2, $series);
        $first = $series->first();
        $this->assertIsArray($first);
        $this->assertSame('2025-01-01 00:00:00', $first['valid_from']);
        $this->assertArrayHasKey('import_value_inc_vat', $first);
        $this->assertArrayHasKey('export_value_inc_vat', $first);
    }
}
