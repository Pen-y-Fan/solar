<?php

namespace Tests\Feature\Filament;

use App\Domain\Strategy\Models\Strategy;
use App\Domain\Strategy\ValueObjects\CostData;
use App\Domain\User\Models\User;
use App\Filament\Resources\StrategyResource;
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

        Carbon::setTestNow(Carbon::today()->setTime(12, 0, 0));

        // Create and authenticate a user using the factory
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $this->actingAs($this->user);
    }

    public function testCanViewListStrategies(): void
    {
        // Create some strategies
        Strategy::factory()->create([
            'period' => now()->startOfDay()->addHours(2),
        ]);

        Strategy::factory()->create([
            'period' => now()->startOfDay()->addHours(3),
        ]);

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
        $strategy = Strategy::factory()->create([
            'period' => now()->startOfDay()->addHours(2),
        ]);

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

    public function testCostDataIntegrationInForms(): void
    {
        // Create a strategy with cost-related properties
        $importValue = 25.50;
        $exportValue = 10.25;
        $consumptionAverage = 5.0;
        $consumptionLastWeek = 6.0;

        $strategy = Strategy::factory()->create([
            'period' => now()->startOfDay()->addHours(2),
            'import_value_inc_vat' => $importValue,
            'export_value_inc_vat' => $exportValue,
            'consumption_average' => $consumptionAverage,
            'consumption_last_week' => $consumptionLastWeek,
            'consumption_average_cost' => $consumptionAverage * $importValue,
            'consumption_last_week_cost' => $consumptionLastWeek * $importValue,
        ]);

        // Refresh to ensure all calculations are applied
        $strategy->refresh();

        // Test the edit form displays cost-related fields correctly
        Livewire::actingAs($this->user)
            ->test(StrategyResource\Pages\EditStrategy::class, [
                'record' => $strategy->id,
            ])
            ->assertSuccessful()
            ->assertFormSet([
                'import_value_inc_vat' => $importValue,
                'export_value_inc_vat' => $exportValue,
                'consumption_average_cost' => $consumptionAverage * $importValue,
                'consumption_last_week_cost' => $consumptionLastWeek * $importValue,
            ]);

        // Test the list table displays cost-related columns correctly
        // Note: We can't directly test computed columns like net_cost and best_consumption_cost
        // in the table assertion, but we can verify the base data is there
        Livewire::actingAs($this->user)
            ->test(StrategyResource\Pages\ListStrategies::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$strategy]);

        // Verify that the CostData value object is working correctly
        $costData = $strategy->getCostDataValueObject();
        $this->assertInstanceOf(CostData::class, $costData);
        $this->assertEquals($importValue, $costData->importValueIncVat);
        $this->assertEquals($exportValue, $costData->exportValueIncVat);
        $this->assertEquals($consumptionAverage * $importValue, $costData->consumptionAverageCost);
        $this->assertEquals($consumptionLastWeek * $importValue, $costData->consumptionLastWeekCost);

        // Verify utility methods
        $this->assertEquals($importValue - $exportValue, $costData->getNetCost());
        $this->assertTrue($costData->isImportCostHigher());
        $this->assertEquals($consumptionLastWeek * $importValue, $costData->getBestConsumptionCostEstimate());
    }
}
