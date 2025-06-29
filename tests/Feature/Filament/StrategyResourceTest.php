<?php

namespace Tests\Feature\Filament;

use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use App\Filament\Resources\StrategyResource;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class StrategyResourceTest extends TestCase
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

    public function testCanViewListStrategies(): void
    {
        // Create some strategies
        Strategy::factory()->count(5)->create();

        // Test the Livewire component
        Livewire::actingAs($this->user)
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Strategy::all());
    }

    public function testCanEditStrategy(): void
    {
        // Create a strategy
        $strategy = Strategy::factory()->create([
            'period' => Carbon::parse('2024-06-15 00:00:00')->timezone('UTC'),
            'battery_percentage_manual' => 50,
            'consumption_manual' => 100,
        ]);

        // Test the Livewire component for editing
        Livewire::actingAs($this->user)
            ->test(StrategyResource\Pages\EditStrategy::class, [
                'record' => $strategy->id,
            ])
            ->assertSuccessful()
            ->assertFormSet([
                'battery_percentage_manual' => 50,
                'consumption_manual' => 100,
            ])
            ->fillForm([
                'battery_percentage_manual' => 75,
                'consumption_manual' => 150,
                'strategy_manual' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert the strategy was updated
        $this->assertDatabaseHas('strategies', [
            'id' => $strategy->id,
            'battery_percentage_manual' => 75,
            'consumption_manual' => 150,
            'strategy_manual' => true,
        ]);
    }

    public function testCanDeleteStrategy(): void
    {
        // Create a strategy
        $strategy = Strategy::factory()->create();

        // Test the Livewire component for bulk deletion
        Livewire::actingAs($this->user)
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$strategy])
            ->callTableBulkAction('delete', [$strategy->id])
            ->assertSuccessful();

        // Assert the strategy was deleted
        $this->assertDatabaseMissing('strategies', [
            'id' => $strategy->id,
        ]);
    }
}
