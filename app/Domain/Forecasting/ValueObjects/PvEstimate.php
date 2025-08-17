<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

/**
 * Value object representing PV (photovoltaic) estimates for solar forecasting
 */
class PvEstimate
{
    /**
     * @param float|null $estimate Main PV estimate
     * @param float|null $estimate10 Lower bound estimate (10th percentile)
     * @param float|null $estimate90 Upper bound estimate (90th percentile)
     */
    public function __construct(
        public readonly ?float $estimate = null,
        public readonly ?float $estimate10 = null,
        public readonly ?float $estimate90 = null
    ) {
        if ($estimate !== null && $estimate < 0) {
            throw new \InvalidArgumentException('PV estimate cannot be negative');
        }

        if ($estimate10 !== null && $estimate10 < 0) {
            throw new \InvalidArgumentException('PV estimate 10th percentile cannot be negative');
        }

        if ($estimate90 !== null && $estimate90 < 0) {
            throw new \InvalidArgumentException('PV estimate 90th percentile cannot be negative');
        }
    }

    /**
     * Create from an array with 'pv_estimate', 'pv_estimate10', and 'pv_estimate90' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            estimate: isset($data['pv_estimate']) ? (float) $data['pv_estimate'] : null,
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
     * Convert to array with only the main estimate
     * (for use with ActualForecast which only has pv_estimate)
     */
    public function toSingleArray(): array
    {
        return [
            'pv_estimate' => $this->estimate,
        ];
    }

    /**
     * Create a simplified PvEstimate with only the main estimate
     * (for use with ActualForecast which only has pv_estimate)
     */
    public static function fromSingleEstimate(?float $estimate): self
    {
        return new self(
            estimate: $estimate
        );
    }
}
