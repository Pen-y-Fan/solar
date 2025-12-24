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
            $cost = $strategy->getCostDataValueObject();
            $net = (float)$cost->getNetCost();

            return [
                'valid_from' => (string)$strategy->period,
                'import_value_inc_vat' => $cost->importValueIncVat,
                'export_value_inc_vat' => $cost->exportValueIncVat,
                'net_cost' => $net,
            ];
        });

        return $collection;
    }
}
