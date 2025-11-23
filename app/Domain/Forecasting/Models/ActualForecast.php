<?php

namespace App\Domain\Forecasting\Models;

use App\Domain\Energy\Models\Inverter;
use App\Domain\Forecasting\ValueObjects\PvEstimate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Solcast actual forecast model.
 *
 * @property int $id
 * @property \Carbon\CarbonImmutable|null $period_end
 * @property float|null $pv_estimate
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Inverter|null $inverter
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActualForecast newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActualForecast newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActualForecast query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActualForecast whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActualForecast whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActualForecast wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActualForecast wherePvEstimate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActualForecast whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ActualForecast extends Model
{
//    use HasFactory;

    protected $fillable = [
        'period_end',
        'pv_estimate',
    ];

    protected $casts = [
        'period_end' => 'immutable_datetime',
    ];

    public function inverter(): HasOne
    {
        return $this->hasOne(Inverter::class, 'period', 'period_end');
    }

    /**
     * Get the PV estimate value object
     */
    public function getPvEstimateValueObject(): PvEstimate
    {
        return PvEstimate::fromSingleEstimate($this->pv_estimate);
    }

    /**
     * Set the PV estimate from a value object
     */
    public function setPvEstimateValueObject(PvEstimate $pvEstimate): void
    {
        $this->pv_estimate = $pvEstimate->estimate;
    }
}
