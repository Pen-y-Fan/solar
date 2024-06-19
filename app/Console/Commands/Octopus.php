<?php

namespace App\Console\Commands;

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
    protected $description = 'Fetch Octopus data from their API and save it to the database';

    /**
     * Execute the console command.
     */
    public function handle(OctopusImport $octopusImport)
    {
        $this->info('Running Octopus action!');
        try {
            $octopusImport->run();
            $this->info('Octopus import has been fetched!');
        } catch (\Throwable $th) {
            Log::error('Error running Octopus import action:', ['error message' => $th->getMessage()]);
            $this->error($th->getMessage());
        }
    }
}
