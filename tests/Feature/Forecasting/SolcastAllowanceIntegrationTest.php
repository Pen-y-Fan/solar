<?php

declare(strict_types=1);

namespace Tests\Feature\Forecasting;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Forecasting\RequestSolcastActual;
use App\Application\Commands\Forecasting\RequestSolcastForecast;
use App\Domain\Forecasting\Actions\ActualForecastAction;
use App\Domain\Forecasting\Actions\ForecastAction;
use App\Domain\User\Models\User;
use App\Support\Actions\ActionResult;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class SolcastAllowanceIntegrationTest extends TestCase
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

        // Default: no backoff, generous cap
        config([
            'solcast.allowance.daily_cap' => 10,
            'solcast.allowance.forecast_min_interval' => 'PT4H',
            'solcast.allowance.actual_min_interval' => 'PT8H',
            'solcast.allowance.backoff_429' => 'PT8H',
            'solcast.allowance.reset_tz' => 'UTC',
        ]);

        Carbon::setTestNow(now()->startOfHour());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testCombinedDailyCapAcrossEndpoints(): void
    {
        // Cap = 2, no min interval to focus on cap behavior
        config([
            'solcast.allowance.daily_cap' => 2,
            'solcast.allowance.forecast_min_interval' => 'PT0H',
            'solcast.allowance.actual_min_interval' => 'PT0H',
        ]);

        // Stub actions to succeed
        $this->app->instance(ForecastAction::class, new class extends ForecastAction
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });
        $this->app->instance(ActualForecastAction::class, new class extends ActualForecastAction
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });

        /** @var CommandBus $bus */
        $bus = app(CommandBus::class);

        $r1 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertTrue($r1->isSuccess());

        $r2 = $bus->dispatch(new RequestSolcastActual());
        $this->assertTrue($r2->isSuccess());

        $r3 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertFalse($r3->isSuccess(), 'Third request should be skipped due to daily cap');
        $this->assertSame('skipped', $r3->getCode());
    }

    public function testMinIntervalEnforcedUnlessForced(): void
    {
        config([
            'solcast.allowance.daily_cap' => 5,
            'solcast.allowance.forecast_min_interval' => 'PT4H',
        ]);

        $this->app->instance(ForecastAction::class, new class extends ForecastAction
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });

        /** @var CommandBus $bus */
        $bus = app(CommandBus::class);

        // First allowed
        $r1 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertTrue($r1->isSuccess());

        // Immediate repeat should be skipped (under min interval)
        $r2 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertFalse($r2->isSuccess());
        $this->assertSame('skipped', $r2->getCode());

        // Forced should bypass min interval
        $r3 = $bus->dispatch(new RequestSolcastForecast(force: true));
        $this->assertTrue($r3->isSuccess());
    }

    public function testBackoffOn429BlocksSubsequentAttempts(): void
    {
        config([
            'solcast.allowance.daily_cap' => 5,
            'solcast.allowance.forecast_min_interval' => 'PT0H',
            'solcast.allowance.backoff_429' => 'PT6H',
        ]);

        // First attempt returns a 429-like failure message to trigger backoff
        $this->app->instance(ForecastAction::class, new class extends ForecastAction
        {
            public function execute(): ActionResult
            {
                return ActionResult::failure('HTTP 429 rate limit');
            }
        });

        /** @var CommandBus $bus */
        $bus = app(CommandBus::class);

        $r1 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertFalse($r1->isSuccess());

        // Swap to success action but attempts should be skipped due to backoff
        $this->app->instance(ForecastAction::class, new class extends ForecastAction
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });

        $r2 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertFalse($r2->isSuccess(), 'Should be skipped due to backoff');
        $this->assertSame('skipped', $r2->getCode());

        // Advance time past backoff
        Carbon::setTestNow(now()->addHours(7));
        $r3 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertTrue($r3->isSuccess(), 'Backoff expired; should be allowed');
    }

    public function testResetAfterBoundaryAllowsNewRequests(): void
    {
        // Set tiny cap to demonstrate reset behavior
        config([
            'solcast.allowance.daily_cap' => 1,
            'solcast.allowance.forecast_min_interval' => 'PT0H',
            'solcast.allowance.actual_min_interval' => 'PT0H',
            'solcast.allowance.reset_tz' => 'UTC',
        ]);

        $this->app->instance(ForecastAction::class, new class extends ForecastAction
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });

        /** @var CommandBus $bus */
        $bus = app(CommandBus::class);

        // Consume the only allowed request
        $r1 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertTrue($r1->isSuccess());

        // Another immediately should be skipped due to cap
        $r2 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertFalse($r2->isSuccess());

        // Advance to after the computed reset time; the service computes reset_at in UTC day boundary by default.
        // Move to tomorrow at 00:01 UTC
        Carbon::setTestNow(now('UTC')->startOfDay()->addDay()->addMinute());

        // Now it should be allowed again
        $r3 = $bus->dispatch(new RequestSolcastForecast());
        $this->assertTrue($r3->isSuccess());
    }
}
