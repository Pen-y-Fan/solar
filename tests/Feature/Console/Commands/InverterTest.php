<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\Inverter;
use App\Models\Inverter as InverterModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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

    public function testInverterConsoleCommand(): void
    {
        // Arrange
        Log::shouldReceive('info')->atLeast()->once();

        // Act
        $this->artisan((new Inverter())->getName())
            ->expectsOutputToContain('Finding inverter data.')
            ->expectsOutputToContain('File processed and moved to:')
            ->expectsOutputToContain('uploads/processed/' . self::FILE_NAME)
            ->expectsOutputToContain('Successfully imported inverter data!')
            ->assertSuccessful();

        // Assert
        $this->assertDatabaseCount(InverterModel::class, 4);

        $deleted = Storage::delete(
            'uploads/processed/' . self::FILE_NAME,
        );

        $this->assertTrue($deleted, 'Failed to delete test fixture file from storage.');
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
