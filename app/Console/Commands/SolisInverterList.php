<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Solis\Actions\InverterListAction;
use Illuminate\Console\Command;

class SolisInverterList extends Command
{
    /**
     * @var string
     */
    protected $signature = 'solis:inverter-list';

    /**
     * @var string
     */
    protected $description = "Fetch the inverter list from the Solis API and find the inverter id (PoC)";

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Fetching Solis inverter list...');

        // TODO: Add InverterListCommand
        $action = new InverterListAction();
        $result = $action->execute();

        if ($result->isSuccess()) {
            $this->info(sprintf('✅ Success! Full Response logged to storage/logs/laravel-%s.log', date('Y-m-d')));
            $this->info(sprintf('Inverter id: %s', $result->getData()['inverterId']));
            return self::SUCCESS;
        }

        $this->error('❌ Failed: ' . (str($result->getMessage() ?? 'Unknown error')->limit(200)));
        return self::FAILURE;
    }
}
