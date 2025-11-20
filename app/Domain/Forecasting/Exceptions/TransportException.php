<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Exceptions;

final class TransportException extends \RuntimeException
{
    public function __construct(string $message = 'Transport error', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
