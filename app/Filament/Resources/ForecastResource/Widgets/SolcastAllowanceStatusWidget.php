<?php

namespace App\Filament\Resources\ForecastResource\Widgets;

use App\Application\Queries\Forecasting\SolcastAllowanceStatusQuery;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SolcastAllowanceStatusWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        /** @var SolcastAllowanceStatusQuery $query */
        $query = app(SolcastAllowanceStatusQuery::class);
        $status = $query->run();

        $now = Carbon::now()->toImmutable();
        $remaining = $status->remainingBudget();

        $resetLabel = $status->resetAt?->timezone('Europe/London')->format('D jS M H:i') ?? 'n/a';

        $backoffActive = $status->isBackoffActive($now);
        $backoffLabel = $backoffActive
            ? 'Until ' . $status->backoffUntil?->timezone('Europe/London')->format('D jS M H:i')
            : 'None';

        $backoffStat = Stat::make('Backoff', $backoffLabel);
        if ($backoffActive) {
            $backoffStat = $backoffStat->color('warning');
        }

        return [
            Stat::make('Remaining allowance', (string) $remaining . ' / ' . (string) $status->cap),
            Stat::make('Next reset', $resetLabel),
            $backoffStat,
        ];
    }
}
