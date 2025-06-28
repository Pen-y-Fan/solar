<?php

declare(strict_types=1);

namespace App\Domain\Energy\Repositories;

use App\Domain\Energy\ValueObjects\InverterConsumptionData;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Interface for accessing Inverter data
 */
interface InverterRepositoryInterface
{
    /**
     * Get average consumption data grouped by time of day
     *
     * @param CarbonInterface $startDate The start date for the average calculation
     * @return Collection<InverterConsumptionData> Collection of InverterConsumptionData objects
     */
    public function getAverageConsumptionByTime(CarbonInterface $startDate): Collection;

    /**
     * Get consumption data for a specific date range
     *
     * @param CarbonInterface $startDate The start date for the range
     * @param CarbonInterface $endDate The end date for the range
     * @return Collection<InverterConsumptionData> Collection of InverterConsumptionData objects
     */
    public function getConsumptionForDateRange(CarbonInterface $startDate, CarbonInterface $endDate): Collection;
}
