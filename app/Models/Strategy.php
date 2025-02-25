<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Strategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'battery_percentage',
        'strategy_manual',
        'strategy1',
        'strategy2',
        'consumption_last_week',
        'consumption_average',
        'consumption_manual',
        'import_value_inc_vat',
        'export_value_inc_vat',
    ];

    protected $casts = [
        'period' => 'immutable_datetime'
    ];

    public function importCost(): HasOne
    {
        return $this->hasOne(AgileImport::class, 'valid_from', 'period');
    }

    public function exportCost(): HasOne
    {
        return $this->hasOne(AgileExport::class, 'valid_from', 'period');
    }

    public function forecast(): HasOne
    {
        return $this->hasOne(Forecast::class, 'period_end', 'period');
    }

    public function actualForecast(): HasOne
    {
        return $this->hasOne(ActualForecast::class, 'period_end', 'period');
    }
}
