<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property \Carbon\CarbonImmutable|null $period_end
 * @property float|null $pv_estimate
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|ActualForecast newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ActualForecast newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ActualForecast query()
 * @method static \Illuminate\Database\Eloquent\Builder|ActualForecast whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ActualForecast whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ActualForecast wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ActualForecast wherePvEstimate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ActualForecast whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ActualForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_end',
        'pv_estimate',
    ];

    protected $casts = [
        'period_end' => 'immutable_datetime',
    ];
}
