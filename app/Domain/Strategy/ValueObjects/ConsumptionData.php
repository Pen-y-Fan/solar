<?php

declare(strict_types=1);

namespace App\Domain\Strategy\ValueObjects;

/**
 * Value object representing consumption data for strategy planning
 */
class ConsumptionData
{
    /**
     * @param float|null $lastWeek Consumption from last week
     * @param float|null $average Average consumption
     * @param float|null $manual Manually set consumption (if applicable)
     */
    public function __construct(
        public readonly ?float $lastWeek = null,
        public readonly ?float $average = null,
        public readonly ?float $manual = null
    ) {
        if ($lastWeek !== null && $lastWeek < 0) {
            throw new \InvalidArgumentException('Last week consumption cannot be negative');
        }

        if ($average !== null && $average < 0) {
            throw new \InvalidArgumentException('Average consumption cannot be negative');
        }

        if ($manual !== null && $manual < 0) {
            throw new \InvalidArgumentException('Manual consumption cannot be negative');
        }
    }

    /**
     * Create from an array with 'consumption_last_week', 'consumption_average', and 'consumption_manual' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lastWeek: isset($data['consumption_last_week']) ? (float) $data['consumption_last_week'] : null,
            average: isset($data['consumption_average']) ? (float) $data['consumption_average'] : null,
            manual: isset($data['consumption_manual']) ? (float) $data['consumption_manual'] : null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'consumption_last_week' => $this->lastWeek,
            'consumption_average' => $this->average,
            'consumption_manual' => $this->manual,
        ];
    }

    /**
     * Check if manual consumption is set
     */
    public function hasManualConsumption(): bool
    {
        return $this->manual !== null;
    }

    /**
     * Get the best consumption estimate (prioritizing manual, then last week, then average)
     */
    public function getBestEstimate(): ?float
    {
        if ($this->manual !== null) {
            return $this->manual;
        }

        if ($this->lastWeek !== null) {
            return $this->lastWeek;
        }

        return $this->average;
    }

    /**
     * Check if there is any consumption data available
     */
    public function hasData(): bool
    {
        return $this->lastWeek !== null || $this->average !== null || $this->manual !== null;
    }
}
