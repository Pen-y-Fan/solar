<?php

declare(strict_types=1);

namespace App\Application\Commands\Bus;

use App\Application\Commands\Contracts\Command;
use App\Support\Actions\ActionResult;
use Illuminate\Contracts\Container\Container;

final class SimpleCommandBus implements CommandBus
{
    /** @var array<class-string<Command>, class-string> */
    private array $map;

    public function __construct(private readonly Container $container, array $map = [])
    {
        $this->map = $map;
    }

    /**
     * Register a handler mapping.
     * @param class-string<Command> $command
     * @param class-string $handler
     */
    public function register(string $command, string $handler): void
    {
        $this->map[$command] = $handler;
    }

    public function dispatch(Command $command): ActionResult
    {
        $commandClass = $command::class;
        $handlerClass = $this->map[$commandClass] ?? null;
        if ($handlerClass === null) {
            // try conventional resolution: {Command}Handler
            $handlerClass = $commandClass . 'Handler';
        }

        $handler = $this->container->make($handlerClass);

        return $handler->handle($command);
    }
}
