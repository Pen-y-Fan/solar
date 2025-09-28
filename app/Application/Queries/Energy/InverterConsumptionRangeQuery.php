<?php

declare(strict_types=1);

namespace App\Application\Queries\Energy;

use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-side query to return inverter consumption within a date range.
 *
 * Returns a collection of arrays: [time => 'HH:MM:SS', value => float]
 */
final class InverterConsumptionRangeQuery
{
    public function __construct(private readonly InverterRepositoryInterface $inverters)
    {
    }

    /**
     * @return Collection<int, array{time: string, value: float}>
     */
    public function run(CarbonInterface $startDate, CarbonInterface $endDate, ?int $limit = 48): Collection
    {
        $items = $this->inverters
            ->getConsumptionForDateRange($startDate, $endDate)
            ->map(static fn($dto) => [
                'time' => (string) $dto->time,
                'value' => (float) $dto->value,
            ]);

        if ($limit !== null) {
            // Use the most recent N items if there are more than $limit
            $items = $items->take($limit);
        }

        return $items;
    }
}
