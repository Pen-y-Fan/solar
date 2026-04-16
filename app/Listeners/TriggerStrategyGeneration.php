<?php

namespace App\Listeners;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GenerateStrategyCommand;
use App\Events\AgileRatesUpdated;
use App\Domain\Energy\Models\AgileImport;
use Illuminate\Support\Carbon;

class TriggerStrategyGeneration
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    /**
     * Handle the event.
     */
    public function handle(AgileRatesUpdated $event): void
    {
        $today4pm = Carbon::today('Europe/London')->setHour(16)->setMinute(0)->setSecond(0);
        $tomorrow4pm = $today4pm->copy()->addDay();

        // Check if we have data for today's 4pm period (16:00 today to 16:00 tomorrow)
        if ($this->hasDataForPeriod($today4pm)) {
            $this->commandBus->dispatch(new GenerateStrategyCommand($today4pm->format('Y-m-d H:i')));
        }

        // Check if we have data for tomorrow's 4pm period (16:00 tomorrow to 16:00 the day after)
        if ($this->hasDataForPeriod($tomorrow4pm)) {
            $this->commandBus->dispatch(new GenerateStrategyCommand($tomorrow4pm->format('Y-m-d H:i')));
        }
    }

    private function hasDataForPeriod(Carbon $start): bool
    {
        $end = $start->copy()->addDay();

        // Strategy needs data for the full 24h period.
        // Agile rates are 30 min intervals.
        // We check if the latest valid_to in DB is at least the end of the period.
        $latest = AgileImport::query()->latest('valid_to')->value('valid_to');

        if (!$latest) {
            return false;
        }

        return Carbon::parse($latest)->greaterThanOrEqualTo($end);
    }
}
