<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
