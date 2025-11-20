<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting\ValueObjects;

use App\Domain\Forecasting\Models\SolcastAllowanceState;
use App\Domain\Forecasting\ValueObjects\AllowanceStatus;
use Carbon\CarbonImmutable;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class AllowanceStatusTest extends TestCase
{
    public function testRemainingBudgetAndBackoff(): void
    {
        $now = new CarbonImmutable('2025-11-20 10:00:00', 'UTC');
        $later = $now->addMinutes(5);

        $status = new AllowanceStatus(
            cap: 10,
            count: 7,
            resetAt: $now->addDay(),
            backoffUntil: $later,
        );

        $this->assertSame(3, $status->remainingBudget());
        $this->assertTrue($status->isBackoffActive($now));
        $this->assertFalse($status->isBackoffActive($later->addSecond()));
    }

    // fromModel factory relies on Eloquent/Laravel container; it will be covered in service/feature tests.

    public function testConstructorGuards(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AllowanceStatus(cap: -1, count: 0);
    }
}
