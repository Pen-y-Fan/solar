<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Commands;

use App\Application\Commands\Strategy\CalculateBatteryCommand;
use App\Application\Commands\Strategy\CalculateBatteryCommandHandler;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\Helpers\DateUtils;
use App\Domain\Strategy\Models\Strategy;
use App\Helpers\CalculateBatteryPercentage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use RuntimeException;
use Tests\TestCase;

final class CalculateBatteryCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function testSuccessCalculatesAndPersistsForGivenDay(): void
    {
        Log::spy();

        // Create two strategies for today and forecasts with pv_estimate = 0 for deterministic result
        $date = now('Europe/London')->format('Y-m-d');
        $start = Carbon::parse($date, 'Europe/London')->startOfDay()->timezone('UTC');

        $s1 = Strategy::factory()->create(['period' => $start->copy()]);
        $s2 = Strategy::factory()->create(['period' => $start->copy()->addMinutes(30)]);
        Forecast::factory()->create(['period_end' => $s1->period, 'pv_estimate' => 0]);
        Forecast::factory()->create(['period_end' => $s2->period, 'pv_estimate' => 0]);

        $handler = new CalculateBatteryCommandHandler(new CalculateBatteryPercentage());
        $result = $handler->handle(new CalculateBatteryCommand(date: $date));

        $this->assertTrue($result->isSuccess());
        $this->assertGreaterThanOrEqual(0, Strategy::find($s1->id)->battery_percentage1);
        $this->assertLessThanOrEqual(100, Strategy::find($s1->id)->battery_percentage1);
        $this->assertGreaterThanOrEqual(0, Strategy::find($s2->id)->battery_percentage1);
        $this->assertLessThanOrEqual(100, Strategy::find($s2->id)->battery_percentage1);

        // Logs include started and finished
        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')->withArgs(function (...$args): bool {
            return isset($args[0]) && $args[0] === 'CalculateBatteryCommand started';
        })->once();

        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')->withArgs(function (...$args): bool {
            if (count($args) < 2) {
                return false;
            }
            /** @var string $message */
            $message = $args[0];
            /** @var array $context */
            $context = $args[1];
            return $message === 'CalculateBatteryCommand finished'
                && ($context['success'] ?? null) === true
                && array_key_exists('ms', $context)
                && ($context['count'] ?? null) === 2;
        })->once();
    }

    public function testFailureIsLoggedAndReturned(): void
    {
        Log::spy();

        // Mock calculator to throw when calculate() is called
        /** @var m\MockInterface&CalculateBatteryPercentage $calculator */
        $calculator = m::mock(CalculateBatteryPercentage::class);
        // @phpstan-ignore-next-line mock expectation
        $calculator->shouldReceive('startBatteryPercentage')->andReturnSelf();
        // @phpstan-ignore-next-line mock expectation
        $calculator->shouldReceive('isCharging')->andReturnSelf();
        // @phpstan-ignore-next-line mock expectation
        $calculator->shouldReceive('consumption')->andReturnSelf();
        // @phpstan-ignore-next-line mock expectation
        $calculator->shouldReceive('estimatePVkWh')->andReturnSelf();
        // @phpstan-ignore-next-line mock expectation
        $calculator->shouldReceive('calculate')->andThrow(new RuntimeException('boom'));

        // Seed one strategy for today so handler iterates
        $date = now('Europe/London')->format('Y-m-d');
        $start = Carbon::parse($date, 'Europe/London')->startOfDay()->timezone('UTC');
        $s1 = Strategy::factory()->create(['period' => $start->copy()]);
        Forecast::factory()->create(['period_end' => $s1->period, 'pv_estimate' => 0]);

        $handler = new CalculateBatteryCommandHandler($calculator);
        $result = $handler->handle(new CalculateBatteryCommand(date: $date));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Battery calculation failed: boom', $result->getMessage());

        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'CalculateBatteryCommand failed'
                && ($context['exception'] ?? null) === 'boom'
                && array_key_exists('ms', $context);
        })->once();
    }

    public function testUsesPreviousPeriodBatteryPercentage(): void
    {
        $date = '2026-01-09 12:00:00';
        [$start, $end] = DateUtils::calculateDateRange1600to1600($date);
        $prevPeriod = $start->copy()->subMinutes(30);

        // Seed prev strategy with battery_manual = 50
        Strategy::factory()->create([
            'period' => $prevPeriod,
            'battery_percentage_manual' => 50,
        ]);

        // Seed first strategy in range: cons=1, pv=0, charging=false, manual=null
        Forecast::factory()->create(['period_end' => $start, 'pv_estimate' => 0.0]);
        $strat1 = Strategy::factory()->create([
            'period' => $start,
            'consumption_manual' => 1.0,
            'strategy_manual' => false,
            'battery_percentage_manual' => null,
        ]);

        $handler = new CalculateBatteryCommandHandler(new CalculateBatteryPercentage());
        $result = $handler->handle(new CalculateBatteryCommand(date: $date));

        $this->assertTrue($result->isSuccess());

        $strat1 = $strat1->fresh();
        // start 50%=2kWh -1kWh cons (pv=0) =1kWh =25%, no import (1>0.4)
        $this->assertSame(25, $strat1->battery_percentage1);
        $this->assertSame(25, $strat1->battery_percentage_manual);
        $this->assertSame(0.0, $strat1->battery_charge_amount);
        $this->assertSame(0.0, $strat1->import_amount);
        $this->assertSame(0.0, $strat1->export_amount);
    }

    public function testUses100WhenNoPreviousPeriodStrategy(): void
    {
        $date = '2026-01-09';
        [$start, $end] = DateUtils::calculateDateRange1600to1600($date);

        // No prev seed

        // Seed first strategy
        $forecast1 = Forecast::factory()->create(['period_end' => $start, 'pv_estimate' => 0.0]);
        $strat1 = Strategy::factory()->create([
            'period' => $start,
            'consumption_manual' => 1.0,
            'strategy_manual' => false,
            'battery_percentage_manual' => null,
        ]);

        $handler = new CalculateBatteryCommandHandler(new CalculateBatteryPercentage());
        $result = $handler->handle(new CalculateBatteryCommand(date: $date));

        $this->assertTrue($result->isSuccess());

        $strat1 = $strat1->fresh();
        // start 100%=4kWh -1=3kWh=75%
        $this->assertSame(75, $strat1->battery_percentage1);
        $this->assertSame(75, $strat1->battery_percentage_manual);
        $this->assertSame(0.0, $strat1->battery_charge_amount);
        $this->assertSame(0.0, $strat1->import_amount);
        $this->assertSame(0.0, $strat1->export_amount);
    }

    public function testChainsBatteryPercentageAcrossPeriods(): void
    {
        $date = '2026-01-09';
        [$start, $end] = DateUtils::calculateDateRange1600to1600($date);
        $period1 = $start;
        $period2 = $start->copy()->addMinutes(30);

        // No prev → start 100

        $forecast1 = \App\Domain\Forecasting\Models\Forecast::factory()->create([
            'period_end' => $period1,
            'pv_estimate' => 0.0,
        ]);
        $forecast2 = \App\Domain\Forecasting\Models\Forecast::factory()->create([
            'period_end' => $period2,
            'pv_estimate' => 0.0,
        ]);

        Strategy::factory()->create([
            'period' => $period1,
            'consumption_manual' => 1.0,
            'strategy_manual' => false,
            'battery_percentage_manual' => null,
        ]);

        Strategy::factory()->create([
            'period' => $period2,
            'consumption_manual' => 1.0,
            'strategy_manual' => false,
            'battery_percentage_manual' => null,
        ]);

        $handler = new CalculateBatteryCommandHandler(new CalculateBatteryPercentage());
        $handler->handle(new CalculateBatteryCommand(date: $date));

        $strat1 = Strategy::where('period', $period1)->first();
        $strat2 = Strategy::where('period', $period2)->first();

        $this->assertSame(75, $strat1->battery_percentage1); // 100% -1kWh =75%
        $this->assertSame(50, $strat2->battery_percentage1); // 75% -1kWh =50%
    }
}
