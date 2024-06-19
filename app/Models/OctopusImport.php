<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
