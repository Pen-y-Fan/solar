<?php

declare(strict_types=1);

namespace App\Domain\Energy\ValueObjects;

/**
 * Value object representing the state of charge of a battery
 */
class BatteryStateOfCharge
{
    /**
     * @param int $percentage Battery state of charge as a percentage (0-100)
     */
    public function __construct(
        public readonly int $percentage
    ) {
        if ($percentage < 0 || $percentage > 100) {
            throw new \InvalidArgumentException('Battery state of charge must be between 0 and 100');
        }
    }

    /**
     * Create from an array with 'battery_soc' key
     */
    public static function fromArray(array $data): self
    {
        return new self(
            percentage: (int) $data['battery_soc']
        );
    }

    /**
     * Create from a percentage value
     */
    public static function fromPercentage(int $percentage): self
    {
        return new self(
            percentage: $percentage
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'battery_soc' => $this->percentage,
        ];
    }

    /**
     * Check if the battery is fully charged
     */
    public function isFullyCharged(): bool
    {
        return $this->percentage === 100;
    }

    /**
     * Check if the battery is fully discharged
     */
    public function isFullyDischarged(): bool
    {
        return $this->percentage === 0;
    }

    /**
     * Get the charge level as a decimal (0.0 - 1.0)
     */
    public function getChargeLevel(): float
    {
        return $this->percentage / 100.0;
    }
}
