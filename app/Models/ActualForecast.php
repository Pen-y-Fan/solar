<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActualForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_end',
        'pv_estimate',
    ];

    protected $casts = [
        'period_end' => 'immutable_datetime',
    ];
}
