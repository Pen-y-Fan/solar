<?php

namespace App\Console\Commands;

use App\Actions\AgileExport;
use App\Actions\AgileImport;
use App\Actions\OctopusExport;
use App\Actions\OctopusImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
    public function handle(
        OctopusImport $octopusImport,
        OctopusExport $octopusExport,
        AgileImport $agileImport,
        AgileExport $agileExport
    ) {
        $this->info('Running Octopus action!');

        try {
            $octopusImport->run();
            $this->info('Octopus import has been fetched!');
        } catch (\Throwable $th) {
            Log::error('Error running Octopus import action:', ['error message' => $th->getMessage()]);
            $this->error('Error running Octopus import action:');
            $this->error($th->getMessage());
        }

        try {
            $octopusExport->run();
            $this->info('Octopus export has been fetched!');
        } catch (\Throwable $th) {
            Log::error('Error running Octopus export action:', ['error message' => $th->getMessage()]);
            $this->error('Error running Octopus export action:');
            $this->error($th->getMessage());
        }

        try {
            $agileImport->run();
            $this->info('Octopus Agile import has been fetched!');
        } catch (\Throwable $th) {
            Log::error('Error running Octopus Agile import action:', ['error message' => $th->getMessage()]);
            $this->error('Error running Octopus Agile import action:');
            $this->error($th->getMessage());
        }

        try {
            $agileExport->run();
            $this->info('Octopus Agile export has been fetched!');
        } catch (\Throwable $th) {
            Log::error('Error running Octopus Agile export action:', ['error message' => $th->getMessage()]);
            $this->error('Error running Octopus Agile export action:');
            $this->error($th->getMessage());
        }
    }
}
