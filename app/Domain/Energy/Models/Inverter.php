<?php

namespace App\Domain\Energy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Carbon\CarbonImmutable|null $period
 * @property float|null $yield
 * @property float|null $to_grid
 * @property float|null $from_grid
 * @property float|null $consumption
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $battery_soc
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter query()
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter whereBatterySoc($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter whereConsumption($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter whereFromGrid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter whereToGrid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Inverter whereYield($value)
 *
 * @mixin \Eloquent
 */
class Inverter extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'yield',
        'to_grid',
        'from_grid',
        'battery_soc',
        'consumption',
    ];

    protected $casts = [
        'period' => 'immutable_datetime',
    ];
}
