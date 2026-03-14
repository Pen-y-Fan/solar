<?php

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Energy\Models\Inverter;
use App\Domain\User\Models\User;
use App\Filament\Widgets\InverterCountChart;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class InverterCountChartTest extends TestCase
{
    use DatabaseMigrations;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2025-03-14 12:00:00'));

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->actingAs($this->user);
    }

    public function testWidgetCanRender(): void
    {
        Livewire::test(InverterCountChart::class)
            ->assertSuccessful();
    }

    public function testWidgetDisplaysCorrectCountsForCurrentMonth(): void
    {
        Inverter::factory()->create(['period' => '2025-03-01 10:00:00']);
        Inverter::factory()->create(['period' => '2025-03-01 11:00:00']);
        Inverter::factory()->create(['period' => '2025-03-14 10:00:00']);

        Livewire::test(InverterCountChart::class)
            ->assertSet('filter', '2025-03')
            ->assertSee('Inverter Records');
    }
}
