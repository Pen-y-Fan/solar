<?php

declare(strict_types=1);

namespace App\Domain\Energy\ValueObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Value object representing consumption data from an inverter
 */
class InverterConsumptionData
{
    /**
     * @param string $time Time in format H:i:s
     * @param float $value Consumption value
     */
    public function __construct(
        public readonly string $time,
        public readonly float $value
    ) {
    }

    /**
     * Create from an array with 'time' and 'value' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            time: $data['time'],
            value: (float) $data['value']
        );
    }

    /**
     * Create from a Carbon date and consumption value
     */
    public static function fromCarbon(CarbonInterface $date, float $consumption): self
    {
        return new self(
            time: $date->format('H:i:s'),
            value: $consumption
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'time' => $this->time,
            'value' => $this->value,
        ];
    }
}
