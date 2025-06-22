<?php

namespace App\Domain\Energy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property float|null $consumption
 * @property \Carbon\CarbonImmutable|null $interval_start
 * @property \Carbon\CarbonImmutable|null $interval_end
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domain\Energy\Models\AgileImport|null $importCost
 *
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport query()
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport whereConsumption($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport whereIntervalEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport whereIntervalStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusImport whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class OctopusImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumption',
        'interval_start',
        'interval_end',
    ];

    protected $casts = [
        'interval_start' => 'immutable_datetime',
        'interval_end' => 'immutable_datetime',
    ];

    public function importCost(): HasOne
    {
        return $this->hasOne(AgileImport::class, 'valid_from', 'interval_start');
    }
}
