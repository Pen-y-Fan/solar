<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
