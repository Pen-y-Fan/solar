<?php

namespace App\Domain\Forecasting\Models;

use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property \Carbon\CarbonImmutable|null $period_end
 * @property float|null $pv_estimate
 * @property float|null $pv_estimate10
 * @property float|null $pv_estimate90
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domain\Energy\Models\AgileExport|null $exportCost
 * @property-read \App\Domain\Energy\Models\AgileImport|null $importCost
 * @property-read \App\Domain\Strategy\Models\Strategy|null $strategy
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast query()
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast wherePvEstimate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast wherePvEstimate10($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast wherePvEstimate90($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Forecast whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Forecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_end',
        'pv_estimate',
        'pv_estimate10',
        'pv_estimate90',
    ];

    protected $casts = [
        'period_end' => 'immutable_datetime',
    ];

    public function importCost(): HasOne
    {
        return $this->hasOne(AgileImport::class, 'valid_from', 'period_end');
    }

    public function exportCost(): HasOne
    {
        return $this->hasOne(AgileExport::class, 'valid_from', 'period_end');
    }

    public function strategy(): HasOne
    {
        return $this->hasOne(Strategy::class, 'period', 'period_end');
    }
}
