<?php

declare(strict_types=1);

namespace App\Application\Queries\Energy;

use App\Domain\Energy\Models\AgileImport;
use Illuminate\Support\Collection;

final class AgileImportExportSeriesQuery
{
    /**
     * Fetch import/export price points from the database for charting.
     *
     * @return Collection<int, array{
     *     valid_from: string,
     *     import_value_inc_vat: float,
     *     export_value_inc_vat: float
     * }>
     */
    public function run(\DateTimeInterface $startUtc, int $limit = 96): Collection
    {
        $importData = AgileImport::query()
            ->with('exportCost')
            ->where('valid_from', '>=', $startUtc)
            ->orderBy('valid_from')
            ->limit($limit)
            ->get();

        return $importData->map(fn($item): array => [
            'valid_from'           => (string)($item->valid_from),
            'import_value_inc_vat' => (float)($item->value_inc_vat ?? 0),
            'export_value_inc_vat' => (float)($item->exportCost ? $item->exportCost->value_inc_vat : 0),
        ]);
    }
}
