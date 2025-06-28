<?php

declare(strict_types=1);

namespace App\Domain\Strategy\ValueObjects;

/**
 * Value object representing battery state information for strategy planning
 */
class BatteryState
{
    /**
     * @param int $percentage Battery percentage target
     * @param float $chargeAmount Amount of energy to charge/discharge
     * @param int|null $manualPercentage Manually set battery percentage (if applicable)
     */
    public function __construct(
        public readonly int $percentage,
        public readonly float $chargeAmount,
        public readonly ?int $manualPercentage = null
    ) {
        if ($percentage < 0 || $percentage > 100) {
            throw new \InvalidArgumentException('Battery percentage must be between 0 and 100');
        }

        if ($manualPercentage !== null && ($manualPercentage < 0 || $manualPercentage > 100)) {
            throw new \InvalidArgumentException('Manual battery percentage must be between 0 and 100');
        }
    }

    /**
     * Create from an array with 'battery_percentage1', 'battery_charge_amount', and 'battery_percentage_manual' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            percentage: (int) $data['battery_percentage1'],
            chargeAmount: (float) $data['battery_charge_amount'],
            manualPercentage: isset($data['battery_percentage_manual'])
                ? (int) $data['battery_percentage_manual']
                : null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'battery_percentage1' => $this->percentage,
            'battery_charge_amount' => $this->chargeAmount,
            'battery_percentage_manual' => $this->manualPercentage,
        ];
    }

    /**
     * Check if manual percentage is set
     */
    public function hasManualPercentage(): bool
    {
        return $this->manualPercentage !== null;
    }

    /**
     * Get the effective percentage (manual if set, otherwise calculated)
     */
    public function getEffectivePercentage(): int
    {
        return $this->manualPercentage ?? $this->percentage;
    }

    /**
     * Check if the battery is charging
     */
    public function isCharging(): bool
    {
        return $this->chargeAmount > 0;
    }

    /**
     * Check if the battery is discharging
     */
    public function isDischarging(): bool
    {
        return $this->chargeAmount < 0;
    }
}
