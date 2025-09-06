<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Strategy\CalculateBatteryCommand;
use App\Application\Commands\Strategy\CalculateBatteryCommandHandler;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\Models\Strategy;
use App\Helpers\CalculateBatteryPercentage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use Tests\TestCase;

final class CalculateBatteryCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function testSuccessCalculatesAndPersistsForGivenDay(): void
    {
        Log::spy();

        // Create two strategies for today and forecasts with pv_estimate = 0 for deterministic result
        $date = now('Europe/London')->format('Y-m-d');
        $start = \Carbon\Carbon::parse($date, 'Europe/London')->startOfDay()->timezone('UTC');

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
        $calculator->shouldReceive('calculate')->andThrow(new \RuntimeException('boom'));

        // Seed one strategy for today so handler iterates
        $date = now('Europe/London')->format('Y-m-d');
        $start = \Carbon\Carbon::parse($date, 'Europe/London')->startOfDay()->timezone('UTC');
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
}
