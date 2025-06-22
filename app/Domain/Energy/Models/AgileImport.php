<?php

namespace App\Domain\Energy\Models;

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
}
