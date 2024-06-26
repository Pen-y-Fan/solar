<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InverterTest extends TestCase
{
    use RefreshDatabase;

    public function test_inverter_console_command(): void
    {
        /**
         * App\Console\Commands\Inverter
         */
        $this->artisan('app:inverter')
            ->expectsOutputToContain('Finding inverter data.')
            ->expectsOutputToContain('Successfully imported inverter data!')
            ->assertSuccessful();

        $this->assertDatabaseCount('inverters', 48);
    }
}
