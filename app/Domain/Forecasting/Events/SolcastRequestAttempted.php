<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Events;

use App\Domain\Forecasting\ValueObjects\Endpoint;
use Carbon\CarbonImmutable;

final class SolcastRequestAttempted
{
    public function __construct(
        public readonly Endpoint $endpoint,
        public readonly CarbonImmutable $at
    ) {
    }
}
