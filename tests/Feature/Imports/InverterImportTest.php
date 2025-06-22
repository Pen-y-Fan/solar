<?php

namespace Tests\Feature\Imports;

use App\Imports\InverterImport;
use App\Models\Inverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelReader;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class InverterImportTest extends TestCase
{
    use RefreshDatabase;

    private const FILE_NAME = 'Inverter Test History Report_20250221.xls';

    private const UPLOADS_TESTS_DIRECTORY = 'uploads/tests/';

    private const TESTS_FIXTURES_DIRECTORY = 'tests/Fixtures/';

    public function testAnInverterImportCanCreateInverterData(): void
    {
        // Arrange
        $this->setupFixture();

        // Act
        $this->importInverterData();

        // Assert
        $inverters = Inverter::all();

        $this->assertEqualsWithDelta(0.2, $inverters->sum('yield'), 0.001);
        $this->assertEqualsWithDelta(0, $inverters->sum('to_grid'), 0.001);
        $this->assertEqualsWithDelta(1.1, $inverters->sum('consumption'), 0.001);
        $this->assertEqualsWithDelta(3.0, $inverters->sum('from_grid'), 0.001);
        $this->assertEquals(99, $inverters->max('battery_soc'));
        $this->assertEquals(53, $inverters->min('battery_soc'));
    }

    public function testAInverterCanBeUpsertedForTheSamePeriod(): void
    {
        // Arrange
        $this->setupFixture();
        $this->importInverterData();
        $this->assertDatabaseCount('inverters', 4);

        // Act
        $this->importInverterData();

        // Assert
        $this->assertDatabaseCount('inverters', 4);
    }

    public function setupFixture(): void
    {
        if (Storage::exists(self::UPLOADS_TESTS_DIRECTORY . self::FILE_NAME)) {
            return;
        }

        $source = base_path(self::TESTS_FIXTURES_DIRECTORY . self::FILE_NAME);
        $this->assertTrue(file_exists($source), "Fixture file not found at: $source");

        $destination = self::UPLOADS_TESTS_DIRECTORY . self::FILE_NAME;
        $copy = Storage::put($destination, file_get_contents($source));
        $this->assertTrue($copy, "Failed to copy fixture file to storage: $destination");
    }

    private function importInverterData(): void
    {
        Excel::import(
            new InverterImport(),
            self::UPLOADS_TESTS_DIRECTORY . self::FILE_NAME,
            null,
            ExcelReader::XLS
        );
    }
}
