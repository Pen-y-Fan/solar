<?php

namespace App\Domain\Strategy\Models;

use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\ValueObjects\BatteryState;
use App\Domain\Strategy\ValueObjects\ConsumptionData;
use Database\Factories\StrategyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property \Carbon\CarbonImmutable|null $period
 * @property int|null $battery_percentage1
 * @property float|null $battery_charge_amount
 * @property float|null $import_amount
 * @property float|null $export_amount
 * @property int|null $battery_percentage_manual
 * @property int|null $strategy_manual
 * @property int|null $strategy1
 * @property int|null $strategy2
 * @property float|null $consumption_last_week
 * @property float|null $consumption_average
 * @property float|null $consumption_manual
 * @property float|null $import_value_inc_vat
 * @property float|null $export_value_inc_vat
 * @property float|null $consumption_average_cost
 * @property float|null $consumption_last_week_cost
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ActualForecast|null $actualForecast
 * @property-read \App\Domain\Energy\Models\AgileExport|null $exportCost
 * @property-read \App\Domain\Forecasting\Models\Forecast|null $forecast
 * @property-read \App\Domain\Energy\Models\AgileImport|null $importCost
 *
 * @method static StrategyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy query()
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereBatteryChargeAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereBatteryPercentage1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereBatteryPercentageManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereConsumptionAverage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereConsumptionAverageCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereConsumptionLastWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereConsumptionLastWeekCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereConsumptionManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereExportAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereExportValueIncVat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereImportAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereImportValueIncVat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereStrategy1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereStrategy2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereStrategyManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Strategy whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Strategy extends Model
{
    use HasFactory;

    /**
     * The ConsumptionData value object
     */
    private ?ConsumptionData $consumptionDataObject = null;

    /**
     * The BatteryState value object
     */
    private ?BatteryState $batteryStateObject = null;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return StrategyFactory::new();
    }

    protected $fillable = [
        'period',
        'battery_charge_amount',
        'import_amount',
        'export_amount',
        'battery_percentage_manual',
        'strategy_manual',
        'strategy1',
        'strategy2',
        'consumption_last_week',
        'consumption_average',
        'consumption_manual',
        'import_value_inc_vat',
        'export_value_inc_vat',
        'consumption_average_cost',
        'consumption_last_week_cost',
    ];

    protected $casts = [
        'period' => 'immutable_datetime',
    ];

    public function importCost(): HasOne
    {
        return $this->hasOne(AgileImport::class, 'valid_from', 'period');
    }

    public function exportCost(): HasOne
    {
        return $this->hasOne(AgileExport::class, 'valid_from', 'period');
    }

    public function forecast(): HasOne
    {
        return $this->hasOne(Forecast::class, 'period_end', 'period');
    }

    public function actualForecast(): HasOne
    {
        return $this->hasOne(ActualForecast::class, 'period_end', 'period');
    }

    /**
     * Get the ConsumptionData value object
     */
    public function getConsumptionDataValueObject(): ConsumptionData
    {
        if ($this->consumptionDataObject === null) {
            $this->consumptionDataObject = ConsumptionData::fromArray([
                'consumption_last_week' => $this->attributes['consumption_last_week'] ?? null,
                'consumption_average' => $this->attributes['consumption_average'] ?? null,
                'consumption_manual' => $this->attributes['consumption_manual'] ?? null,
            ]);
        }

        return $this->consumptionDataObject;
    }

    /**
     * Get the BatteryState value object
     */
    public function getBatteryStateValueObject(): BatteryState
    {
        if ($this->batteryStateObject === null) {
            $this->batteryStateObject = BatteryState::fromArray([
                'battery_percentage1' => $this->attributes['battery_percentage1'] ?? 0,
                'battery_charge_amount' => $this->attributes['battery_charge_amount'] ?? 0.0,
                'battery_percentage_manual' => $this->attributes['battery_percentage_manual'] ?? null,
            ]);
        }

        return $this->batteryStateObject;
    }

    /**
     * Get the consumption last week
     */
    public function getConsumptionLastWeekAttribute($value): ?float
    {
        return $this->getConsumptionDataValueObject()->lastWeek;
    }

    /**
     * Set the consumption last week
     */
    public function setConsumptionLastWeekAttribute(?float $value): void
    {
        $this->attributes['consumption_last_week'] = $value;

        if ($this->consumptionDataObject !== null) {
            $this->consumptionDataObject = new ConsumptionData(
                lastWeek: $value,
                average: $this->consumptionDataObject->average,
                manual: $this->consumptionDataObject->manual
            );
        }
    }

    /**
     * Get the consumption average
     */
    public function getConsumptionAverageAttribute($value): ?float
    {
        return $this->getConsumptionDataValueObject()->average;
    }

    /**
     * Set the consumption average
     */
    public function setConsumptionAverageAttribute(?float $value): void
    {
        $this->attributes['consumption_average'] = $value;

        if ($this->consumptionDataObject !== null) {
            $this->consumptionDataObject = new ConsumptionData(
                lastWeek: $this->consumptionDataObject->lastWeek,
                average: $value,
                manual: $this->consumptionDataObject->manual
            );
        }
    }

    /**
     * Get the consumption manual
     */
    public function getConsumptionManualAttribute($value): ?float
    {
        return $this->getConsumptionDataValueObject()->manual;
    }

    /**
     * Set the consumption manual
     */
    public function setConsumptionManualAttribute(?float $value): void
    {
        $this->attributes['consumption_manual'] = $value;

        if ($this->consumptionDataObject !== null) {
            $this->consumptionDataObject = new ConsumptionData(
                lastWeek: $this->consumptionDataObject->lastWeek,
                average: $this->consumptionDataObject->average,
                manual: $value
            );
        }
    }

    /**
     * Get the battery percentage
     */
    public function getBatteryPercentage1Attribute($value): int
    {
        return $this->getBatteryStateValueObject()->percentage;
    }

    /**
     * Set the battery percentage
     */
    public function setBatteryPercentage1Attribute(int $value): void
    {
        $this->attributes['battery_percentage1'] = $value;

        if ($this->batteryStateObject !== null) {
            $this->batteryStateObject = new BatteryState(
                percentage: $value,
                chargeAmount: $this->batteryStateObject->chargeAmount,
                manualPercentage: $this->batteryStateObject->manualPercentage
            );
        }
    }

    /**
     * Get the battery charge amount
     */
    public function getBatteryChargeAmountAttribute($value): float
    {
        return $this->getBatteryStateValueObject()->chargeAmount;
    }

    /**
     * Set the battery charge amount
     */
    public function setBatteryChargeAmountAttribute(float $value): void
    {
        $this->attributes['battery_charge_amount'] = $value;

        if ($this->batteryStateObject !== null) {
            $this->batteryStateObject = new BatteryState(
                percentage: $this->batteryStateObject->percentage,
                chargeAmount: $value,
                manualPercentage: $this->batteryStateObject->manualPercentage
            );
        }
    }

    /**
     * Get the battery percentage manual
     */
    public function getBatteryPercentageManualAttribute($value): ?int
    {
        return $this->getBatteryStateValueObject()->manualPercentage;
    }

    /**
     * Set the battery percentage manual
     */
    public function setBatteryPercentageManualAttribute(?int $value): void
    {
        $this->attributes['battery_percentage_manual'] = $value;

        if ($this->batteryStateObject !== null) {
            $this->batteryStateObject = new BatteryState(
                percentage: $this->batteryStateObject->percentage,
                chargeAmount: $this->batteryStateObject->chargeAmount,
                manualPercentage: $value
            );
        }
    }
}
