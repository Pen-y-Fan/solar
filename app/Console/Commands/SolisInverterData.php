<?php

namespace App\Console\Commands;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GetInverterDayDataCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SolisInverterData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'solis:inverter-data {date? : Date in YYYY-MM-DD format, defaults to yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and upsert daily inverter data from Solis API';

    /**
     * Execute the console command.
     */
    public function handle(CommandBus $bus): int
    {
        $date = $this->argument('date') ?? Carbon::yesterday()->format('Y-m-d');

        $bus->dispatch(new GetInverterDayDataCommand($date));

        $this->info(sprintf('Solis inverter data upsert completed for %s', $date));

        return self::SUCCESS;
    }
}
