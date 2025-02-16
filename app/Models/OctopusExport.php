<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
}
