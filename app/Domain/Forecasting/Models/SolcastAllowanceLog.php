<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_type
 * @property string|null $endpoint
 * @property string|null $reason
 * @property int|null $status
 * @property Carbon|null $backoff_until
 * @property string|null $day_key
 * @property Carbon|null $reset_at
 * @property Carbon|null $next_eligible_at
 * @property array|null $payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SolcastAllowanceLog extends Model
{
    use HasFactory;
    use Prunable;

    protected $table = 'solcast_allowance_logs';

    protected $casts = [
        'backoff_until' => 'immutable_datetime',
        'reset_at' => 'immutable_datetime',
        'next_eligible_at' => 'immutable_datetime',
        'payload' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    protected $fillable = [
        'event_type',
        'endpoint',
        'reason',
        'status',
        'backoff_until',
        'day_key',
        'reset_at',
        'next_eligible_at',
        'payload',
        'created_at',
    ];

    /**
     * Prune records older than configured retention.
     */
    public function prunable(): Builder
    {
        $days = (int) config('solcast.allowance.log_max_days', 14);
        $cutoff = Carbon::now()->subDays($days);
        return static::query()->where('created_at', '<', $cutoff);
    }
}
