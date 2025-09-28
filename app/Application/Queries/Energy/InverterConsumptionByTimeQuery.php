<?php

declare(strict_types=1);

namespace App\Application\Queries\Energy;

use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-side query to return inverter consumption aggregated by time of day.
 *
 * Returns a collection of arrays: [time => 'HH:MM:SS', value => float]
 */
final class InverterConsumptionByTimeQuery
{
    public function __construct(private readonly InverterRepositoryInterface $inverters)
    {
    }

    /**
     * @return Collection<int, array{time: string, value: float}>
     */
    public function run(?CarbonInterface $startDate = null): Collection
    {
        $start = $startDate ?: Carbon::now('Europe/London')->startOfDay();

        return $this->inverters
            ->getAverageConsumptionByTime($start)
            ->map(fn($dto) => [
                'time' => (string) $dto->time,
                'value' => (float) $dto->value,
            ]);
    }
}
