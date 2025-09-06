<?php

declare(strict_types=1);

namespace App\Application\Commands\Bus;

use App\Application\Commands\Contracts\Command;
use App\Support\Actions\ActionResult;

interface CommandBus
{
    public function dispatch(Command $command): ActionResult;
}
