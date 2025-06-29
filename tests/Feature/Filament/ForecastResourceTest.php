<?php

namespace Tests\Feature\Filament;

use App\Domain\Forecasting\Models\Forecast;
use App\Domain\User\Models\User;
use App\Filament\Resources\ForecastResource;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ForecastResourceTest extends TestCase
{
    use DatabaseMigrations;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate a user using the factory
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($this->user);
    }

    public function testCanViewListForecasts(): void
    {
        // Create some forecasts
        Forecast::factory()->count(5)->create();

        // Test the Livewire component
        Livewire::actingAs($this->user)
            ->test(ForecastResource\Pages\ListForecasts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Forecast::all());
    }

    public function testCanCreateForecast(): void
    {
        $periodEnd = Carbon::parse('2024-06-15 00:00:00')->timezone('UTC');

        // Test the Livewire component for creating
        Livewire::actingAs($this->user)
            ->test(ForecastResource\Pages\CreateForecast::class)
            ->assertSuccessful()
            ->fillForm([
                'period_end' => $periodEnd,
                'pv_estimate' => 100,
                'pv_estimate10' => 80,
                'pv_estimate90' => 120,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Assert the forecast was created
        $this->assertDatabaseHas('forecasts', [
            'pv_estimate' => 100,
            'pv_estimate10' => 80,
            'pv_estimate90' => 120,
        ]);
    }

    public function testCanEditForecast(): void
    {
        // Create a forecast
        $forecast = Forecast::factory()->create([
            'period_end' => Carbon::parse('2024-06-15 00:00:00')->timezone('UTC'),
            'pv_estimate' => 100,
            'pv_estimate10' => 80,
            'pv_estimate90' => 120,
        ]);

        // Test the Livewire component for editing
        Livewire::actingAs($this->user)
            ->test(ForecastResource\Pages\EditForecast::class, [
                'record' => $forecast->id,
            ])
            ->assertSuccessful()
            ->assertFormSet([
                'pv_estimate' => 100,
                'pv_estimate10' => 80,
                'pv_estimate90' => 120,
            ])
            ->fillForm([
                'pv_estimate' => 150,
                'pv_estimate10' => 120,
                'pv_estimate90' => 180,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert the forecast was updated
        $this->assertDatabaseHas('forecasts', [
            'id' => $forecast->id,
            'pv_estimate' => 150,
            'pv_estimate10' => 120,
            'pv_estimate90' => 180,
        ]);
    }

    public function testCanDeleteForecast(): void
    {
        // Create a forecast
        $forecast = Forecast::factory()->create();

        // Test the Livewire component for bulk deletion
        Livewire::actingAs($this->user)
            ->test(ForecastResource\Pages\ListForecasts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$forecast])
            ->callTableBulkAction('delete', [$forecast->id])
            ->assertSuccessful();

        // Assert the forecast was deleted
        $this->assertDatabaseMissing('forecasts', [
            'id' => $forecast->id,
        ]);
    }
}
