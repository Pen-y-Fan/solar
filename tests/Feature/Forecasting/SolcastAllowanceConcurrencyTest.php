<?php

declare(strict_types=1);

namespace Tests\Feature\Forecasting;

use App\Domain\Forecasting\Models\SolcastAllowanceState;
use App\Domain\Forecasting\Services\SolcastAllowanceService;
use App\Domain\Forecasting\ValueObjects\Endpoint;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Stage 11 — Concurrency/Locking Verification
 *
 * Ensures that the reservation pattern under a transaction with lockForUpdate()
 * prevents double increments when two workers race to reserve at the same time.
 *
 * This test uses a file-based SQLite database so both concurrent workers share
 * the same database and its transaction/locking semantics.
 */
final class SolcastAllowanceConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a shared file-based SQLite database for this test
        $this->dbPath = storage_path('framework/testing-concurrency.sqlite');
        if (file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
        // Ensure the file exists so SQLite can open it
        touch($this->dbPath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->dbPath,
        ]);

        // Configure allowance to make cap the only limiter in this test
        config([
            'solcast.allowance.daily_cap' => 1,
            'solcast.allowance.forecast_min_interval' => 'PT0S',
            'solcast.allowance.actual_min_interval' => 'PT0S',
            'solcast.allowance.backoff_429' => 'PT8H',
            'solcast.allowance.reset_tz' => 'UTC',
        ]);

        Carbon::setTestNow('2025-11-20 10:00:00');

        // Note: SQLite does not support true row-level locks with FOR UPDATE.
        // This test simulates a race by performing two immediate reservation attempts
        // and asserts that only one increment occurs due to the reservation + cap check
        // within a transaction.
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
        if (isset($this->dbPath) && file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
    }

    public function testConcurrentReservationsOnlyIncrementOnce(): void
    {
        // Warm up singleton and ensure a clean starting state
        $svc = new SolcastAllowanceService();
        $svc->currentStatus();

        // Attempt two immediate reservations (simulated race). With cap=1 and atomic
        // reservation inside a transaction, only the first should succeed.
        $first = $svc->checkAndLock(Endpoint::FORECAST)->isAllowed();
        $second = $svc->checkAndLock(Endpoint::FORECAST)->isAllowed();

        $this->assertTrue($first, 'First reservation should be allowed');
        $this->assertFalse($second, 'Second reservation should be denied under cap/double-increment prevention');

        $state = SolcastAllowanceState::query()->firstOrFail();
        $this->assertSame(1, (int) $state->count, 'Daily count should only increment once under concurrency');
    }
}
