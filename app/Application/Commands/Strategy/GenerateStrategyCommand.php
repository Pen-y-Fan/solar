<?php

declare(strict_types=1);

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;

final class GenerateStrategyCommand implements Command
{
    public function __construct(
        public readonly string $period
    ) {
    }
}
