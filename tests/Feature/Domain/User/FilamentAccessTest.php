<?php

namespace Tests\Feature\Domain\User;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class FilamentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanAccessFilamentInProductionWithProperInterface(): void
    {
        // Force environment to production
        App::detectEnvironment(fn() => 'production');
        $this->assertEquals('production', App::environment());

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/forecasts');

        // Now it should be 200 because we implemented FilamentUser
        $response->assertStatus(200);
    }

    public function testUserCanAccessFilamentInLocal(): void
    {
        // Force environment to local
        App::detectEnvironment(fn() => 'local');
        $this->assertEquals('local', App::environment());

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/forecasts');

        // In local, Filament often allows access or redirects to login if not authenticated
        // Since we are actingAs(user), it should be 200
        $response->assertStatus(200);
    }
}
