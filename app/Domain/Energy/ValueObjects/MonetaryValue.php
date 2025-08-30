<?php

declare(strict_types=1);

namespace App\Domain\Energy\ValueObjects;

/**
 * Value object representing monetary values for energy costs
 */
class MonetaryValue
{
    /**
     * @param float|null $excVat Value excluding VAT
     * @param float|null $incVat Value including VAT
     */
    public function __construct(
        public readonly ?float $excVat = null,
        public readonly ?float $incVat = null
    )
    {
    }

    /**
     * Create from an array with 'value_exc_vat' and 'value_inc_vat' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            excVat: isset($data['value_exc_vat']) ? (float)$data['value_exc_vat'] : null,
            incVat: isset($data['value_inc_vat']) ? (float)$data['value_inc_vat'] : null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'value_exc_vat' => $this->excVat,
            'value_inc_vat' => $this->incVat,
        ];
    }

    /**
     * Get the VAT amount (difference between incVat and excVat)
     */
    public function getVatAmount(): ?float
    {
        if ($this->incVat === null || $this->excVat === null) {
            return null;
        }

        return $this->incVat - $this->excVat;
    }

    /**
     * Get the VAT rate as a percentage
     */
    public function getVatRate(): ?float
    {
        if ($this->incVat === null || $this->excVat === null || $this->excVat === 0.0) {
            return null;
        }

        return (($this->incVat / $this->excVat) - 1) * 100;
    }
}
