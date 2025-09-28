<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Commands;

use App\Application\Commands\Strategy\RecalculateStrategyCostsCommand;
use App\Application\Commands\Strategy\RecalculateStrategyCostsCommandHandler;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class RecalculateStrategyCostsCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function testSuccessRecalculatesCostsForGivenDay(): void
    {
        Log::spy();

        $date = now('Europe/London')->format('Y-m-d');
        $start = \Carbon\Carbon::parse($date, 'Europe/London')->startOfDay()->timezone('UTC');

        $s1 = Strategy::factory()->create([
            'period' => $start->copy(),
            'consumption_average' => 2.0,
            'consumption_last_week' => 1.5,
            'import_value_inc_vat' => 0.30,
            'consumption_average_cost' => null,
            'consumption_last_week_cost' => null,
        ]);
        $s2 = Strategy::factory()->create([
            'period' => $start->copy()->addMinutes(30),
            'consumption_average' => null, // should stay null
            'consumption_last_week' => 3.0,
            'import_value_inc_vat' => 0.25,
            'consumption_average_cost' => 999, // will be corrected to null
            'consumption_last_week_cost' => null,
        ]);

        $handler = new RecalculateStrategyCostsCommandHandler();
        $result = $handler->handle(new RecalculateStrategyCostsCommand(dateFrom: $date));

        $this->assertTrue($result->isSuccess());
        $this->assertSame(2.0 * 0.30, (float) Strategy::find($s1->id)->consumption_average_cost);
        $this->assertSame(1.5 * 0.30, (float) Strategy::find($s1->id)->consumption_last_week_cost);

        $reloaded2 = Strategy::find($s2->id);
        $this->assertNull($reloaded2->consumption_average_cost);
        $this->assertSame(3.0 * 0.25, (float) $reloaded2->consumption_last_week_cost);

        // Logs include started and finished with updated count >= 1
        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'RecalculateStrategyCostsCommand started';
        })->once();
        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'RecalculateStrategyCostsCommand finished'
                && ($context['success'] ?? null) === true
                && array_key_exists('updated', $context)
                && array_key_exists('ms', $context);
        })->once();
    }

    public function testInvalidDateFailsAndLogsWarning(): void
    {
        Log::spy();

        $handler = new RecalculateStrategyCostsCommandHandler();
        $result = $handler->handle(new RecalculateStrategyCostsCommand(dateFrom: 'bad-date'));

        $this->assertFalse($result->isSuccess());
        $this->assertStringStartsWith('Recalculate costs failed:', (string) $result->getMessage());

        // @phpstan-ignore-next-line mock expectation
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'RecalculateStrategyCostsCommand failed' && array_key_exists('ms', $context);
        })->once();
    }
}
