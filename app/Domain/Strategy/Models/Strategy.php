<?php

namespace App\Domain\Strategy\Models;

use App\Domain\Forecasting\Models\ActualForecast;
use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Forecasting\Models\Forecast;
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
}
