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
 * @property \Carbon\CarbonImmutable|null $backoff_until
 * @property string|null $day_key
 * @property \Carbon\CarbonImmutable|null $reset_at
 * @property \Carbon\CarbonImmutable|null $next_eligible_at
 * @property array<array-key, mixed>|null $payload
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static Builder<static>|SolcastAllowanceLog newModelQuery()
 * @method static Builder<static>|SolcastAllowanceLog newQuery()
 * @method static Builder<static>|SolcastAllowanceLog query()
 * @method static Builder<static>|SolcastAllowanceLog whereBackoffUntil($value)
 * @method static Builder<static>|SolcastAllowanceLog whereCreatedAt($value)
 * @method static Builder<static>|SolcastAllowanceLog whereDayKey($value)
 * @method static Builder<static>|SolcastAllowanceLog whereEndpoint($value)
 * @method static Builder<static>|SolcastAllowanceLog whereEventType($value)
 * @method static Builder<static>|SolcastAllowanceLog whereId($value)
 * @method static Builder<static>|SolcastAllowanceLog whereNextEligibleAt($value)
 * @method static Builder<static>|SolcastAllowanceLog wherePayload($value)
 * @method static Builder<static>|SolcastAllowanceLog whereReason($value)
 * @method static Builder<static>|SolcastAllowanceLog whereResetAt($value)
 * @method static Builder<static>|SolcastAllowanceLog whereStatus($value)
 * @method static Builder<static>|SolcastAllowanceLog whereUpdatedAt($value)
 * @mixin \Eloquent
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
