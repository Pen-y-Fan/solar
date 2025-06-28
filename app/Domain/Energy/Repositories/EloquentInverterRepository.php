<?php

declare(strict_types=1);

namespace App\Domain\Energy\Repositories;

use App\Domain\Energy\Models\Inverter;
use App\Domain\Energy\ValueObjects\InverterConsumptionData;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of InverterRepositoryInterface
 */
class EloquentInverterRepository implements InverterRepositoryInterface
{
    /**
     * Get average consumption data grouped by time of day
     *
     * @param CarbonInterface $startDate The start date for the average calculation
     * @return Collection<InverterConsumptionData> Collection of InverterConsumptionData objects
     */
    public function getAverageConsumptionByTime(CarbonInterface $startDate): Collection
    {
        $averageConsumptions = Inverter::query()
            ->select(DB::raw('time(period) as `time`, avg(`consumption`) as `value`'))
            ->where(
                'period',
                '>',
                $startDate->timezone('Europe/London')
                    ->subdays(21)
                    ->startOfDay()
                    ->timezone('UTC')
            )
            ->groupBy('time')
            ->get();

        return $averageConsumptions->map(function ($item) {
            return new InverterConsumptionData(
                time: $item->time,
                value: (float) $item->value
            );
        });
    }

    /**
     * Get consumption data for a specific date range
     *
     * @param CarbonInterface $startDate The start date for the range
     * @param CarbonInterface $endDate The end date for the range
     * @return Collection<InverterConsumptionData> Collection of InverterConsumptionData objects
     */
    public function getConsumptionForDateRange(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $consumptions = Inverter::query()
            ->whereBetween('period', [
                $startDate,
                $endDate,
            ])
            ->get();

        return $consumptions->map(function ($consumption) {
            return new InverterConsumptionData(
                time: $consumption->period->format('H:i:s'),
                value: (float) $consumption->consumption
            );
        });
    }
}
