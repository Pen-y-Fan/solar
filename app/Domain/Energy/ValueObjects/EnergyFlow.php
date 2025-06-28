<?php

declare(strict_types=1);

namespace App\Domain\Energy\ValueObjects;

/**
 * Value object representing energy flow data
 */
class EnergyFlow
{
    /**
     * @param float $yield Energy generated
     * @param float $toGrid Energy exported to the grid
     * @param float $fromGrid Energy imported from the grid
     * @param float $consumption Energy consumed
     */
    public function __construct(
        public readonly float $yield,
        public readonly float $toGrid,
        public readonly float $fromGrid,
        public readonly float $consumption
    ) {
    }

    /**
     * Create from an array with 'yield', 'to_grid', 'from_grid', and 'consumption' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            yield: (float) ($data['yield'] ?? 0.0),
            toGrid: (float) ($data['to_grid'] ?? 0.0),
            fromGrid: (float) ($data['from_grid'] ?? 0.0),
            consumption: (float) ($data['consumption'] ?? 0.0)
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'yield' => $this->yield,
            'to_grid' => $this->toGrid,
            'from_grid' => $this->fromGrid,
            'consumption' => $this->consumption,
        ];
    }

    /**
     * Calculate self-consumption (energy generated and consumed locally)
     */
    public function getSelfConsumption(): float
    {
        return $this->yield - $this->toGrid;
    }

    /**
     * Calculate self-sufficiency (percentage of consumption covered by own generation)
     */
    public function getSelfSufficiency(): float
    {
        if ($this->consumption === 0.0) {
            return 0.0;
        }

        return ($this->yield - $this->toGrid) / $this->consumption * 100.0;
    }

    /**
     * Calculate net energy flow (positive means net export, negative means net import)
     */
    public function getNetFlow(): float
    {
        return $this->toGrid - $this->fromGrid;
    }
}
