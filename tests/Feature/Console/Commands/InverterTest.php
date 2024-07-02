<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InverterTest extends TestCase
{
    use RefreshDatabase;

    public function test_inverter_console_command(): void
    {
        Storage::copy(
            'tests/Inverter Test History Report_20240625044020_1300386381676952160.xls',
            'uploads/Inverter Test History Report_20240625044020_1300386381676952160.xls'
        );

        /**
         * App\Console\Commands\Inverter
         */
        $this->artisan('app:inverter')
            ->expectsOutputToContain('Finding inverter data.')
            ->expectsOutputToContain('File processed and moved to:')
            ->expectsOutputToContain('uploads/processed/Inverter Test History Report_20240625044020_1300386381676952160.xls')
            ->expectsOutputToContain('Successfully imported inverter data!')
            ->assertSuccessful();

        $this->assertDatabaseCount('inverters', 48);

        Storage::delete(
            'uploads/processed/Inverter Test History Report_20240625044020_1300386381676952160.xls',
        );
    }
}
