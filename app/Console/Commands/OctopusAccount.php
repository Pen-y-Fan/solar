<?php

namespace App\Console\Commands;

use App\Domain\Energy\Actions\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OctopusAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:octopus-account';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Octopus account information and save it to the log file';

    /**
     * Execute the console command.
     */
    public function handle(Account $account)
    {
        $this->info('Running Octopus account action!');

        try {
            $result = $account->execute();
            if ($result->isSuccess()) {
                $this->info('Octopus account has been fetched!');
            } else {
                $this->warn('Octopus account fetch failed: ' . ($result->getMessage() ?? 'unknown error'));
            }
        } catch (\Throwable $th) {
            Log::error('Error running Octopus account action:', ['error message' => $th->getMessage()]);
            $this->error('Error running Octopus account action:');
            $this->error($th->getMessage());
        }
    }
}
