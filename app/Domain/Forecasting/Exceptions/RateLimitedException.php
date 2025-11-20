<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Exceptions;

final class RateLimitedException extends \RuntimeException
{
    public function __construct(string $message = 'Rate limited', int $code = 429)
    {
        parent::__construct($message, $code);
    }
}
