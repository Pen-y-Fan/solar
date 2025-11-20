<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Exceptions;

final class ClientErrorException extends \RuntimeException
{
    public function __construct(string $message = 'Client error', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
