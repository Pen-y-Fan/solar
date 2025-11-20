<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Exceptions;

final class ServerErrorException extends \RuntimeException
{
    public function __construct(string $message = 'Server error', int $code = 500)
    {
        parent::__construct($message, $code);
    }
}
