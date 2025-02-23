<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InverterTest extends TestCase
{
    use RefreshDatabase;

    private const FILE_NAME = 'Inverter Test History Report_20250221.xls';

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange
        $this->setupFixture();
    }

    public function test_inverter_console_command(): void
    {
        // Act

        // App\Console\Commands\Inverter
        $this->artisan('app:inverter')
            ->expectsOutputToContain('Finding inverter data.')
            ->expectsOutputToContain('File processed and moved to:')
            ->expectsOutputToContain('uploads/processed/' . self::FILE_NAME)
            ->expectsOutputToContain('Successfully imported inverter data!')
            ->assertSuccessful();

        // Assert
        $this->assertDatabaseCount('inverters', 48);

        $deleted = Storage::delete(
            "uploads/processed/" . self::FILE_NAME,
        );

        $this->assertTrue($deleted);
    }

    public function setupFixture(): void
    {
        $source = base_path('tests/Fixtures/' . self::FILE_NAME);
        $this->assertTrue(file_exists($source), 'Fixture file not found at: $source');

        $destination = 'uploads/' . self::FILE_NAME;
        $copy = Storage::put($destination, file_get_contents($source));
        $this->assertTrue($copy);
    }
}
