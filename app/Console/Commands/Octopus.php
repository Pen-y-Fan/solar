<?php

namespace App\Console\Commands;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Energy\ExportAgileRatesCommand;
use App\Application\Commands\Energy\ExportOctopusUsageCommand;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Application\Commands\Energy\ImportOctopusUsageCommand;
use App\Application\Commands\Energy\SyncOctopusAccountCommand;
use Illuminate\Console\Command;

class Octopus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:octopus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Octopus usage and cost data from their API and save it to the database';

    /**
     * Execute the console command.
     */
    public function handle(CommandBus $commandBus): void
    {
        $this->info('Running Octopus action!');

        $commands = [
            'Octopus account sync' => new SyncOctopusAccountCommand(),
            'Octopus usage import' => new ImportOctopusUsageCommand(),
            'Octopus usage export' => new ExportOctopusUsageCommand(),
            'Octopus Agile import' => new ImportAgileRatesCommand(),
            'Octopus Agile export' => new ExportAgileRatesCommand(),
        ];

        foreach ($commands as $label => $command) {
            $this->info("Running $label...");
            $result = $commandBus->dispatch($command);

            if ($result->isSuccess()) {
                $this->info("$label successful: " . ($result->getMessage() ?? 'OK'));
            } else {
                $this->error("$label failed: " . ($result->getMessage() ?? 'unknown error'));
            }
        }
    }
}
