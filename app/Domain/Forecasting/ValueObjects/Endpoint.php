<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

/**
 * Solcast endpoints covered by the allowance policy.
 */
enum Endpoint: string
{
    case FORECAST = 'forecast';
    case ACTUAL = 'actual';

    public function isForecast(): bool
    {
        return $this === self::FORECAST;
    }

    public function isActual(): bool
    {
        return $this === self::ACTUAL;
    }
}
