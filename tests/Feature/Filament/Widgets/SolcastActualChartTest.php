<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Contracts\Command;
use App\Application\Commands\Forecasting\RequestSolcastActual;
use App\Support\Actions\ActionResult;
use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Widgets\SolcastActualChart;

final class SolcastActualChartTest extends TestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user);
    }

    public function testSolcastActualChartTriggersCommandDispatchWhenNoData(): void
    {
        $calls = (object) ['count' => 0];

        // Bind a fake CommandBus to observe dispatches
        $fakeBus = new class ($calls) implements CommandBus
        {
            public function __construct(private object $calls)
            {
            }

            public function dispatch(Command $command): ActionResult
            {
                if ($command instanceof RequestSolcastActual) {
                    $this->calls->count++;
                }

                return ActionResult::success();
            }
        };
        $this->app->instance(CommandBus::class, $fakeBus);

        Livewire::actingAs($this->user)
            ->test(SolcastActualChart::class)
            ->assertSuccessful();

        $this->assertSame(1, $calls->count, 'RequestSolcastActual should be dispatched once when no data exists');
    }

    public function testSolcastActualChartRendersWithSeededDataWithoutUpdate(): void
    {
        // Seed a few ActualForecast records within the last day with recent updated_at
        $base = now()->startOfHour();
        $rows = [
            ['period_end' => $base->clone()->subHours(3), 'pv_estimate' => 0.5],
            ['period_end' => $base->clone()->subHours(2), 'pv_estimate' => 0.7],
            ['period_end' => $base->clone()->subHours(1), 'pv_estimate' => 0.4],
            ['period_end' => $base->clone()->addHour(), 'pv_estimate' => 0.6],
        ];
        foreach ($rows as $r) {
            ActualForecast::create($r);
        }
        // Ensure records are considered fresh to avoid triggering update
        ActualForecast::query()->update(['updated_at' => now()]);

        // Bind a fake CommandBus that would fail the test if dispatch is attempted
        $fakeBus = new class () implements CommandBus
        {
            public function dispatch(Command $command): ActionResult
            {
                throw new \RuntimeException('RequestSolcastActual should not be dispatched for fresh data');
            }
        };
        $this->app->instance(CommandBus::class, $fakeBus);

        Livewire::actingAs($this->user)
            ->test(SolcastActualChart::class)
            ->assertSuccessful();
    }
}
