<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property float|null $value_exc_vat
 * @property float|null $value_inc_vat
 * @property \Carbon\CarbonImmutable|null $valid_from
 * @property \Carbon\CarbonImmutable|null $valid_to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport query()
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport whereValueExcVat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgileExport whereValueIncVat($value)
 * @mixin \Eloquent
 */
class AgileExport extends Model
{
    use HasFactory;

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
}
