<?php

declare(strict_types=1);

namespace App\Domain\Strategy\ValueObjects;

/**
 * Value object representing cost data for strategy planning
 */
class CostData
{
    /**
     * @param float|null $importValueIncVat Import cost including VAT
     * @param float|null $exportValueIncVat Export value including VAT
     * @param float|null $consumptionAverageCost Cost of average consumption
     * @param float|null $consumptionLastWeekCost Cost of last week's consumption
     */
    public function __construct(
        public readonly ?float $importValueIncVat = null,
        public readonly ?float $exportValueIncVat = null,
        public readonly ?float $consumptionAverageCost = null,
        public readonly ?float $consumptionLastWeekCost = null
    ) {
    }

    /**
     * Create from an array with cost data keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            importValueIncVat: isset($data['import_value_inc_vat']) ? (float) $data['import_value_inc_vat'] : null,
            exportValueIncVat: isset($data['export_value_inc_vat']) ? (float) $data['export_value_inc_vat'] : null,
            consumptionAverageCost: isset($data['consumption_average_cost']) ? (float) $data['consumption_average_cost'] : null,
            consumptionLastWeekCost: isset($data['consumption_last_week_cost']) ? (float) $data['consumption_last_week_cost'] : null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'import_value_inc_vat' => $this->importValueIncVat,
            'export_value_inc_vat' => $this->exportValueIncVat,
            'consumption_average_cost' => $this->consumptionAverageCost,
            'consumption_last_week_cost' => $this->consumptionLastWeekCost,
        ];
    }

    /**
     * Calculate the net cost (import cost minus export value)
     */
    public function getNetCost(): ?float
    {
        if ($this->importValueIncVat === null || $this->exportValueIncVat === null) {
            return null;
        }

        return $this->importValueIncVat - $this->exportValueIncVat;
    }

    /**
     * Check if import cost is higher than export value
     */
    public function isImportCostHigher(): ?bool
    {
        if ($this->importValueIncVat === null || $this->exportValueIncVat === null) {
            return null;
        }

        return $this->importValueIncVat > $this->exportValueIncVat;
    }

    /**
     * Get the best consumption cost estimate (prioritizing last week, then average)
     */
    public function getBestConsumptionCostEstimate(): ?float
    {
        if ($this->consumptionLastWeekCost !== null) {
            return $this->consumptionLastWeekCost;
        }

        return $this->consumptionAverageCost;
    }
}
