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
                    ->subDays(21)
                    ->startOfDay()
                    ->timezone('UTC')
            )
            ->groupBy('time')
            ->get();

        return $averageConsumptions->map(function ($item) {
            $value = (float) $item->value;
            // Guard against negative averages due to data glitches
            $value = max(0.0, $value);

            return new InverterConsumptionData(
                time: $item->time,
                value: $value
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
            ->select(['period', 'consumption'])
            ->whereBetween('period', [
                $startDate,
                $endDate,
            ])
            ->orderBy('period')
            ->get();

        // Optional downsampling for UI charts over multi‑day horizons (Proposal D)
        if ((bool) config('perf.inverter_downsample', false)) {
            $bucket = max(1, (int) config('perf.inverter_bucket_minutes', 30));

            $consumptions = $consumptions
                ->groupBy(function ($row) use ($bucket) {
                    /** @var \Illuminate\Support\Carbon $t */
                    $t = $row->period->copy()->timezone('UTC');
                    $minute = (int) $t->format('i');
                    $floored = $minute - ($minute % $bucket);
                    return $t->copy()->minute($floored)->second(0)->format('Y-m-d H:i:s');
                })
                ->map(function ($group) {
                    $first = $group->first();
                    $sum = (float) $group->sum(function ($r) {
                        return (float) $r->consumption;
                    });
                    // Synthesize an object-like shape for downstream mapping
                    return (object) [
                        'period' => $first->period->copy()->timezone('UTC')->setSecond(0),
                        'consumption' => $sum,
                    ];
                })
                ->sortKeys()
                ->values();
        }

        return $consumptions->map(function ($consumption) {
            $value = (float) $consumption->consumption;
            // Guard against any negative readings in the raw data
            $value = max(0.0, $value);

            return new InverterConsumptionData(
                time: $consumption->period->format('H:i:s'),
                value: $value
            );
        });
    }
}
