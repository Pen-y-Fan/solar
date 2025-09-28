<?php

declare(strict_types=1);

namespace App\Application\Queries\Energy;

use App\Domain\Strategy\Models\Strategy;
use Illuminate\Support\Collection;

/**
 * Provides stacked import/export/net costs per Strategy period for charts.
 * Output keys match existing CostChart expectations.
 *
 * Returns collection of:
 *  [
 *    valid_from: string, // UTC datetime string
 *    import_value_inc_vat: float,
 *    export_value_inc_vat: float,
 *    net_cost: float,
 *  ]
 */
final class EnergyCostBreakdownByDayQuery
{
    /**
     * @param Collection<int, Strategy> $strategies Eloquent collection of Strategy models (e.g., page table records)
     * @return Collection<int, array{
     *     valid_from: string,
     *     import_value_inc_vat: float,
     *     export_value_inc_vat: float,
     *     net_cost: float
     * }>
     */
    public function run(Collection $strategies): Collection
    {
        /** @var Collection<int, array{valid_from: string, import_value_inc_vat: float, export_value_inc_vat: float, net_cost: float}> $collection */
        $collection = $strategies->map(static function (Strategy $strategy): array {
            /** @var \Carbon\CarbonImmutable|string|null $period */
            $period = $strategy->period;
            $cost = $strategy->getCostDataValueObject();

            $import = (float)($cost->importValueIncVat ?? 0);
            $export = (float)($cost->exportValueIncVat ?? 0);
            $net = (float)$cost->getNetCost();

            return [
                'valid_from' => (string)$period,
                'import_value_inc_vat' => $import,
                'export_value_inc_vat' => $export,
                'net_cost' => $net,
            ];
        });

        return $collection;
    }
}
