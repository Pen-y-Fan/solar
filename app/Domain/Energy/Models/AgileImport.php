<?php

namespace App\Domain\Energy\Models;

use App\Domain\Energy\ValueObjects\MonetaryValue;
use App\Domain\Energy\ValueObjects\TimeInterval;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property float|null $value_exc_vat
 * @property float|null $value_inc_vat
 * @property \Carbon\CarbonImmutable|null $valid_from
 * @property \Carbon\CarbonImmutable|null $valid_to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domain\Energy\Models\AgileExport|null $exportCost
 *
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport query()
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport whereValueExcVat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileImport whereValueIncVat($value)
 *
 * @mixin \Eloquent
 */
class AgileImport extends Model
{
    use HasFactory;

    /**
     * The MonetaryValue value object
     */
    private ?MonetaryValue $monetaryValueObject = null;

    /**
     * The TimeInterval value object
     */
    private ?TimeInterval $timeIntervalObject = null;

    /**
     * Reset value objects when the model is refreshed
     */
    public function refresh()
    {
        // Reset value objects
        $this->monetaryValueObject = null;
        $this->timeIntervalObject = null;

        // Call parent refresh method
        return parent::refresh();
    }

    protected $fillable = [
        'value_exc_vat',
        'value_inc_vat',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'valid_from' => 'immutable_datetime',
        'valid_to' => 'immutable_datetime',
    ];

    public function exportCost(): HasOne
    {
        return $this->hasOne(AgileExport::class, 'valid_from', 'valid_from');
    }

    /**
     * Get the MonetaryValue value object
     */
    public function getMonetaryValueObject(): MonetaryValue
    {
        if ($this->monetaryValueObject === null) {
            $this->monetaryValueObject = MonetaryValue::fromArray([
                'value_exc_vat' => $this->attributes['value_exc_vat'] ?? null,
                'value_inc_vat' => $this->attributes['value_inc_vat'] ?? null,
            ]);
        }

        return $this->monetaryValueObject;
    }

    /**
     * Get the TimeInterval value object
     */
    public function getTimeIntervalObject(): TimeInterval
    {
        if ($this->timeIntervalObject === null) {
            $this->timeIntervalObject = TimeInterval::fromArray([
                'valid_from' => isset($this->attributes['valid_from']) ?
                    CarbonImmutable::parse($this->attributes['valid_from']) : null,
                'valid_to' => isset($this->attributes['valid_to']) ?
                    CarbonImmutable::parse($this->attributes['valid_to']) : null,
            ]);
        }

        return $this->timeIntervalObject;
    }

    /**
     * Get the value excluding VAT
     */
    public function getValueExcVatAttribute($value): ?float
    {
        return $this->getMonetaryValueObject()->excVat;
    }

    /**
     * Set the value excluding VAT
     */
    public function setValueExcVatAttribute(?float $value): void
    {
        $this->attributes['value_exc_vat'] = $value;

        if ($this->monetaryValueObject !== null) {
            $this->monetaryValueObject = new MonetaryValue(
                excVat: $value,
                incVat: $this->monetaryValueObject->incVat
            );
        }
    }

    /**
     * Get the value including VAT
     */
    public function getValueIncVatAttribute($value): ?float
    {
        return $this->getMonetaryValueObject()->incVat;
    }

    /**
     * Set the value including VAT
     */
    public function setValueIncVatAttribute(?float $value): void
    {
        $this->attributes['value_inc_vat'] = $value;

        if ($this->monetaryValueObject !== null) {
            $this->monetaryValueObject = new MonetaryValue(
                excVat: $this->monetaryValueObject->excVat,
                incVat: $value
            );
        }
    }

    /**
     * Get the valid from time
     */
    public function getValidFromAttribute($value): ?CarbonImmutable
    {
        return $this->getTimeIntervalObject()->from;
    }

    /**
     * Set the valid from time
     */
    public function setValidFromAttribute($value): void
    {
        $this->attributes['valid_from'] = $value;

        if ($this->timeIntervalObject !== null) {
            $this->timeIntervalObject = new TimeInterval(
                from: $value instanceof CarbonImmutable ? $value : CarbonImmutable::parse($value),
                to: $this->timeIntervalObject->to
            );
        }
    }

    /**
     * Get the valid to time
     */
    public function getValidToAttribute($value): ?CarbonImmutable
    {
        return $this->getTimeIntervalObject()->to;
    }

    /**
     * Set the valid to time
     */
    public function setValidToAttribute($value): void
    {
        $this->attributes['valid_to'] = $value;

        if ($this->timeIntervalObject !== null) {
            $this->timeIntervalObject = new TimeInterval(
                from: $this->timeIntervalObject->from,
                to: $value instanceof CarbonImmutable ? $value : CarbonImmutable::parse($value)
            );
        }
    }
}
