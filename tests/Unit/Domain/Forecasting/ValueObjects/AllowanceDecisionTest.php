<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting\ValueObjects;

use App\Domain\Forecasting\ValueObjects\AllowanceDecision;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class AllowanceDecisionTest extends TestCase
{
    public function testAllowFactory(): void
    {
        $d = AllowanceDecision::allow();
        $this->assertTrue($d->allowed);
        $this->assertSame('ok', $d->reason);
        $this->assertNull($d->nextEligibleAt);
        $this->assertTrue($d->isAllowed());
    }

    public function testDenyFactoryWithNextEligible(): void
    {
        $when = new CarbonImmutable('2025-11-20 10:00:00', 'UTC');
        $d = AllowanceDecision::deny('backoff', $when);

        $this->assertFalse($d->allowed);
        $this->assertSame('backoff', $d->reason);
        $this->assertSame($when, $d->nextEligibleAt);
        $this->assertFalse($d->isAllowed());
    }
}
