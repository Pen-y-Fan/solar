<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
}
