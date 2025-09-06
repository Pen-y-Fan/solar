<?php

declare(strict_types=1);

namespace App\Domain\Strategy\Events;

final class StrategyGenerated
{
    public function __construct(
        public readonly string $period
    ) {
    }
}
