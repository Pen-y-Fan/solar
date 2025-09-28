<?php

declare(strict_types=1);

namespace App\Domain\Energy\ValueObjects;

use InvalidArgumentException;

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
            throw new InvalidArgumentException('Battery state of charge must be between 0 and 100');
        }
    }

    /**
     * Create from an array with 'battery_soc' key
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['battery_soc'])) {
            throw new InvalidArgumentException('Array must contain \'battery_soc\' key');
        }

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
     * Create from watt-hours based on a battery capacity in Wh.
     */
    public static function fromWattHours(int $wattHours, int $batteryCapacityWh): self
    {
        if ($batteryCapacityWh <= 0) {
            throw new InvalidArgumentException('Battery capacity must be positive');
        }

        $clampedWh = (int) max(0, min($wattHours, $batteryCapacityWh));
        $percentage = (int) round(($clampedWh / $batteryCapacityWh) * 100);

        return new self($percentage);
    }

    /**
     * Convert current SoC to watt-hours for a given battery capacity in Wh.
     */
    public function toWattHours(int $batteryCapacityWh): int
    {
        if ($batteryCapacityWh <= 0) {
            throw new InvalidArgumentException('Battery capacity must be positive');
        }

        return (int) round(($this->percentage / 100) * $batteryCapacityWh);
    }

    /**
     * Return a new SoC after adding (or subtracting if negative) watt-hours, clamped to [0..100].
     */
    public function withDeltaWattHours(int $deltaWh, int $batteryCapacityWh): self
    {
        $currentWh = $this->toWattHours($batteryCapacityWh);
        $nextWh = $currentWh + $deltaWh;
        return self::fromWattHours($nextWh, $batteryCapacityWh);
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
