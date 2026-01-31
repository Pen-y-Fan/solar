<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Solis\Actions\InverterListAction;
use Illuminate\Console\Command;

class SolisInverterList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'solis:inverter-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Solis inverter list (PoC)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Fetching Solis inverter list...');

        $action = new InverterListAction();
        $result = $action->execute();

        if ($result->isSuccess()) {
            $this->info(sprintf('✅ Success! Response logged to storage/logs/laravel-%s.log', date('Y-m-d')));
            return self::SUCCESS;
        }

        $this->error('❌ Failed: ' . ($result->getMessage() ?? 'Unknown error'));
        return self::FAILURE;
    }
}
