<?php

declare(strict_types=1);

namespace App\Application\Commands\Contracts;

use App\Support\Actions\ActionResult;

/**
 * A handler for a specific Command type.
 */
interface CommandHandler
{
    /**
     * Handle the given command and return an ActionResult.
     */
    public function handle(Command $command): ActionResult;
}
