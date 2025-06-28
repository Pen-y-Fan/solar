<?php

declare(strict_types=1);

namespace App\Domain\Energy\ValueObjects;

/**
 * Value object representing a monetary value with and without VAT
 */
class MonetaryValue
{
    /**
     * @param float $valueExcVat Value excluding VAT
     * @param float $valueIncVat Value including VAT
     */
    public function __construct(
        public readonly float $valueExcVat,
        public readonly float $valueIncVat
    ) {
    }

    /**
     * Create from an array with 'value_exc_vat' and 'value_inc_vat' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            valueExcVat: (float) $data['value_exc_vat'],
            valueIncVat: (float) $data['value_inc_vat']
        );
    }

    /**
     * Create from a value excluding VAT and a VAT rate
     */
    public static function fromValueExcVat(float $valueExcVat, float $vatRate = 0.2): self
    {
        $valueIncVat = $valueExcVat * (1 + $vatRate);

        return new self(
            valueExcVat: $valueExcVat,
            valueIncVat: $valueIncVat
        );
    }

    /**
     * Create from a value including VAT and a VAT rate
     */
    public static function fromValueIncVat(float $valueIncVat, float $vatRate = 0.2): self
    {
        $valueExcVat = $valueIncVat / (1 + $vatRate);

        return new self(
            valueExcVat: $valueExcVat,
            valueIncVat: $valueIncVat
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'value_exc_vat' => $this->valueExcVat,
            'value_inc_vat' => $this->valueIncVat,
        ];
    }

    /**
     * Get the VAT amount
     */
    public function getVatAmount(): float
    {
        return $this->valueIncVat - $this->valueExcVat;
    }

    /**
     * Get the VAT rate
     */
    public function getVatRate(): float
    {
        if ($this->valueExcVat === 0.0) {
            return 0.0;
        }

        return ($this->valueIncVat / $this->valueExcVat) - 1;
    }
}
