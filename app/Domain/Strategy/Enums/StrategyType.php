<?php

declare(strict_types=1);

namespace App\Domain\Strategy\Enums;

enum StrategyType: string
{
    case ManualStrategy = 'manual_strategy';
    case Strategy1 = 'strategy1';
    case Strategy2 = 'strategy2';
}
