<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Decision returned by the allowance policy when attempting a Solcast call.
 */
final class AllowanceDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly ?CarbonImmutable $nextEligibleAt = null,
    ) {
    }

    public static function allow(string $reason = 'ok'): self
    {
        return new self(true, $reason, null);
    }

    /**
     * Create a denial decision with an explanatory reason and optional next-eligible timestamp.
     */
    public static function deny(string $reason, ?CarbonImmutable $nextEligibleAt = null): self
    {
        return new self(false, $reason, $nextEligibleAt);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }
}
