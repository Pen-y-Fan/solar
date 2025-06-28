<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

/**
 * Value object representing photovoltaic (PV) estimates with different confidence levels
 */
class PvEstimate
{
    /**
     * @param float $estimate The median PV estimate (50% confidence)
     * @param float|null $estimate10 The lower bound PV estimate (10% confidence)
     * @param float|null $estimate90 The upper bound PV estimate (90% confidence)
     */
    public function __construct(
        public readonly float $estimate,
        public readonly ?float $estimate10 = null,
        public readonly ?float $estimate90 = null
    ) {
    }

    /**
     * Create from an array with 'pv_estimate', 'pv_estimate10', and 'pv_estimate90' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            estimate: (float) $data['pv_estimate'],
            estimate10: isset($data['pv_estimate10']) ? (float) $data['pv_estimate10'] : null,
            estimate90: isset($data['pv_estimate90']) ? (float) $data['pv_estimate90'] : null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'pv_estimate' => $this->estimate,
            'pv_estimate10' => $this->estimate10,
            'pv_estimate90' => $this->estimate90,
        ];
    }

    /**
     * Get the range of the estimate (difference between upper and lower bounds)
     */
    public function getRange(): ?float
    {
        if ($this->estimate90 === null || $this->estimate10 === null) {
            return null;
        }

        return $this->estimate90 - $this->estimate10;
    }

    /**
     * Get the uncertainty of the estimate as a percentage of the median estimate
     */
    public function getUncertaintyPercentage(): ?float
    {
        if ($this->estimate === 0.0 || $this->getRange() === null) {
            return null;
        }

        return $this->getRange() / $this->estimate * 100.0;
    }

    /**
     * Check if the estimate has confidence bounds
     */
    public function hasConfidenceBounds(): bool
    {
        return $this->estimate10 !== null && $this->estimate90 !== null;
    }
}
