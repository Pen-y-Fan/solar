<?php

namespace App\Application\Commands\Strategy;

use App\Application\Commands\Contracts\Command;

final readonly class GetInverterDayDataCommand implements Command
{
    public function __construct(public readonly string $date)
    {
    }
}
