<?php

namespace Tests\Feature\Filament\Resources;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GetInverterDayDataCommand;
use App\Domain\Energy\Models\Inverter;
use App\Domain\User\Models\User;
use App\Filament\Resources\Energy\Inverters\Pages\ManageInverters;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class InverterResourceTest extends TestCase
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

    public function testCanRenderInverterResourceListPage(): void
    {
        Livewire::test(ManageInverters::class)
            ->assertSuccessful();
    }

    public function testCanFetchInverterData(): void
    {
        $bus = $this->mock(CommandBus::class);
        $bus->shouldReceive('dispatch')
            ->once()
            ->withArgs(
                fn ($command) => $command instanceof GetInverterDayDataCommand && $command->date === '2025-03-13'
            )
            ->andReturn(ActionResult::success());

        Livewire::test(ManageInverters::class)
            ->callAction('fetchInverterData', [
                'date' => '2025-03-13',
            ])
            ->assertHasNoActionErrors();
    }

    public function testCanListInverterDataGroupedByDay(): void
    {
        Inverter::factory()->create([
            'period' => '2025-03-14 10:00:00',
        ]);
        Inverter::factory()->create([
            'period' => '2025-03-14 11:00:00',
        ]);
        Inverter::factory()->create([
            'period' => '2025-03-13 10:00:00',
        ]);

        Livewire::test(ManageInverters::class)
            ->assertCanSeeTableRecords(Inverter::query()
                ->select([
                    DB::raw('MIN(id) as id'),
                    DB::raw('DATE(period) as date'),
                    DB::raw('COUNT(*) as count'),
                ])
                ->groupBy('date')
                ->get());
    }
}
