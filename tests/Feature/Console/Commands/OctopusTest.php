<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OctopusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_console_command(): void
    {
        $this->artisan('app:octopus')
            ->expectsOutputToContain('Running Octopus action!')
            ->expectsOutputToContain('Octopus import has been fetched!')
            ->expectsOutputToContain('Octopus export has been fetched!')
            ->assertSuccessful();
    }
}
