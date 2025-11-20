<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Forecasting\Models\SolcastAllowanceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SolcastPruneLogs extends Command
{
    protected $signature = 'solcast:prune-logs {--days= : Override retention days; defaults to config value}';

    protected $description = 'Prune solcast_allowance_logs older than the configured retention window';

    public function handle(): int
    {
        $daysOpt = $this->option('days');
        $days = is_numeric($daysOpt) ? (int) $daysOpt : (int) config('solcast.allowance.log_max_days', 14);
        if ($days < 0) {
            $this->error('Days must be >= 0');
            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $count = SolcastAllowanceLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$count} log row(s) older than {$days} day(s) (before {$cutoff->toDateTimeString()})");
        return self::SUCCESS;
    }
}
