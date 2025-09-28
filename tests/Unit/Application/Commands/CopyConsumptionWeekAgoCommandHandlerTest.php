<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Commands;

use App\Application\Commands\Strategy\CopyConsumptionWeekAgoCommand;
use App\Application\Commands\Strategy\CopyConsumptionWeekAgoCommandHandler;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class CopyConsumptionWeekAgoCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function testSuccessCopiesLastWeekIntoManualForGivenDay(): void
    {
        Log::spy();

        $date = now('Europe/London')->format('Y-m-d');
        $start = \Carbon\Carbon::parse($date, 'Europe/London')->startOfDay()->timezone('UTC');

        // strategy with lastWeek available
        $s1 = Strategy::factory()->create([
            'period' => $start->copy(),
            'consumption_last_week' => 5.5,
            'consumption_manual' => null,
        ]);
        // strategy without lastWeek
        $s2 = Strategy::factory()->create([
            'period' => $start->copy()->addMinutes(30),
            'consumption_last_week' => null,
            'consumption_manual' => null,
        ]);

        $handler = new CopyConsumptionWeekAgoCommandHandler();
        $result = $handler->handle(new CopyConsumptionWeekAgoCommand(date: $date));

        $this->assertTrue($result->isSuccess());
        $this->assertSame(5.5, Strategy::find($s1->id)->consumption_manual);
        $this->assertNull(Strategy::find($s2->id)->consumption_manual);

        // Logs include started and finished with count 1
        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')->withArgs(function (...$args): bool {
            return isset($args[0]) && $args[0] === 'CopyConsumptionWeekAgoCommand started';
        })->once();

        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')->withArgs(function (...$args): bool {
            if (count($args) < 2) {
                return false;
            }
            $message = $args[0];
            $context = $args[1];
            return $message === 'CopyConsumptionWeekAgoCommand finished'
                && ($context['success'] ?? null) === true
                && ($context['count'] ?? null) === 1
                && array_key_exists('ms', $context);
        })->once();
    }

    public function testFailureOnInvalidDateIsHandledAndLogged(): void
    {
        Log::spy();

        $handler = new CopyConsumptionWeekAgoCommandHandler();
        $result = $handler->handle(new CopyConsumptionWeekAgoCommand(date: 'not-a-date'));

        $this->assertFalse($result->isSuccess());
        $this->assertStringStartsWith('Copy consumption failed:', (string) $result->getMessage());

        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'CopyConsumptionWeekAgoCommand failed' && array_key_exists('ms', $context);
        })->once();
    }
}
