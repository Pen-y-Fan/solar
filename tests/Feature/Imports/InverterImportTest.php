<?php

namespace Tests\Feature\Imports;

use App\Imports\InverterImport;
use App\Models\Inverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class InverterImportTest extends TestCase
{
    use RefreshDatabase;

    private const FILE_NAME = 'Inverter Test History Report_20250221.xls';

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange
        $this->setupFixture();
    }
    public function test_an_inverter_import_can_create_inverter_data(): void
    {
        // Act
        $this->importInverterData();

        // Assert
        $inverters = Inverter::all();

        $this->assertEqualsWithDelta(0.7, $inverters->sum('yield'), 0.001);
        $this->assertEqualsWithDelta(0, $inverters->sum('to_grid'), 0.001);
        $this->assertEqualsWithDelta(12.2, $inverters->sum('consumption'), 0.001);
        $this->assertEqualsWithDelta(12.84, $inverters->sum('from_grid'), 0.001);
        $this->assertEquals(99, $inverters->max('battery_soc'));
        $this->assertEquals(8, $inverters->min('battery_soc'));
    }

    public function test_a_inverter_can_be_upserted_for_the_same_period(): void
    {
        // Arrange
        $this->importInverterData();
        $this->assertDatabaseCount('inverters', 48);

        // Act
        $this->importInverterData();

        // Assert
        $this->assertDatabaseCount('inverters', 48);
    }

    public function setupFixture(): void
    {
        $source = base_path('tests/Fixtures/' . self::FILE_NAME);
        $this->assertTrue(file_exists($source), 'Fixture file not found at: '. $source);

        $destination = 'tests/' . self::FILE_NAME;
        $copy = Storage::put($destination, file_get_contents($source));
        $this->assertTrue($copy);
    }

    private function importInverterData(): void
    {
        Excel::import(
            new InverterImport(),
            'tests/' . self::FILE_NAME,
            null,
            \Maatwebsite\Excel\Excel::XLS
        );
    }
}
