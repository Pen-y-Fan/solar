<?php

declare(strict_types=1);

namespace App\Application\Queries\Forecasting;

use App\Domain\Forecasting\Services\Contracts\SolcastAllowanceContract;
use App\Domain\Forecasting\ValueObjects\AllowanceStatus;

final readonly class SolcastAllowanceStatusQuery
{
    public function __construct(private SolcastAllowanceContract $allowance)
    {
    }

    public function run(): AllowanceStatus
    {
        return $this->allowance->currentStatus();
    }
}
