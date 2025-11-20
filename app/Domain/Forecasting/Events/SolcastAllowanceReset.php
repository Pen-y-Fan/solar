<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Events;

use Carbon\CarbonImmutable;

final class SolcastAllowanceReset
{
    public function __construct(
        public readonly string $dayKey,
        public readonly CarbonImmutable $resetAt,
    ) {
    }
}
