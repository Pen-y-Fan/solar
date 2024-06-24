<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inverter extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'yield',
        'to_grid',
        'from_grid',
        'consumption',
    ];

    protected $casts = [
        'period' => 'immutable_datetime',
    ];
}
