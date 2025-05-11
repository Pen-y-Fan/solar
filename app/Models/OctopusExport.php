<?php

namespace App\Models;

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
 * @property-read \App\Models\AgileExport|null $exportCost
 * @property-read \App\Models\AgileImport|null $importCost
 * @property-read \App\Models\OctopusImport|null $octopusImport
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport query()
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport whereConsumption($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport whereIntervalEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport whereIntervalStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OctopusExport whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OctopusExport extends Model
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

    public function exportCost(): HasOne
    {
        return $this->hasOne(AgileExport::class, 'valid_from', 'interval_start');
    }

    public function octopusImport(): HasOne
    {
        return $this->hasOne(OctopusImport::class, 'interval_start', 'interval_start');
    }

    public function inverter(): HasOne
    {
        return $this->hasOne(Inverter::class, 'period', 'interval_start');
    }
}
