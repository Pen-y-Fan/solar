<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting;

use App\Domain\Forecasting\Models\SolcastAllowanceState;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SolcastAllowanceStateTest extends TestCase
{
    use RefreshDatabase;

    public function testEnsureForNowCreatesSingletonForCurrentDay(): void
    {
        $tz = 'UTC';
        $now = CarbonImmutable::parse('2025-11-20 10:00:00', 'UTC');

        $state = SolcastAllowanceState::ensureForNow($now, $tz);

        $this->assertTrue($state->id > 0);
        $this->assertSame('20251120', $state->day_key);
        $this->assertSame(0, $state->count);

        $expectedReset = SolcastAllowanceState::nextResetAtFor($now, $tz);
        $expectedIso = $expectedReset->toIso8601String();
        $actualIso = CarbonImmutable::parse($state->reset_at)->toIso8601String();
        $this->assertSame($expectedIso, $actualIso);
    }

    public function testEnsureForNowResetsWhenPastResetAt(): void
    {
        $tz = 'UTC';
        $now = CarbonImmutable::parse('2025-11-20 23:59:59', 'UTC');

        // Seed a row for the day
        $state = SolcastAllowanceState::ensureForNow($now, $tz);
        $state->count = 5;
        $state->backoff_until = \Carbon\Carbon::instance($now->addHours(2)->toDateTime());
        $state->save();

        // Move to next day beyond reset_at
        $later = $now->addSeconds(2); // crosses midnight due to nextResetAt at 2025-11-21T00:00:00Z

        $refreshed = SolcastAllowanceState::ensureForNow($later, $tz);

        $this->assertSame('20251121', $refreshed->day_key);
        $this->assertSame(0, $refreshed->count);
        $this->assertNull($refreshed->backoff_until);
        $this->assertNull($refreshed->last_attempt_at_forecast);
        $this->assertNull($refreshed->last_attempt_at_actual);
        $this->assertNull($refreshed->last_success_at_forecast);
        $this->assertNull($refreshed->last_success_at_actual);
    }

    public function testIsBackoffActive(): void
    {
        $tz = 'UTC';
        $now = CarbonImmutable::parse('2025-11-20 10:00:00', 'UTC');
        $state = SolcastAllowanceState::ensureForNow($now, $tz);

        $this->assertFalse($state->isBackoffActive($now));

        $state->backoff_until = \Carbon\Carbon::instance($now->addHour()->toDateTime());
        $state->save();

        $this->assertTrue($state->isBackoffActive($now));
        $this->assertFalse($state->isBackoffActive($now->addHours(2)));
    }
}
