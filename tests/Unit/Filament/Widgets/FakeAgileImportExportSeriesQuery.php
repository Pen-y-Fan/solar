<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use Illuminate\Support\Collection;

/**
 * Simple fake for AgileImportExportSeriesQuery that returns a prepared collection.
 */
final class FakeAgileImportExportSeriesQuery
{
    /** @param Collection<int, array{valid_from:string, import_value_inc_vat: float, export_value_inc_vat: float}> $ret */
    public function __construct(private Collection $ret)
    {
    }

    public function run(\DateTimeInterface $startUtc, int $limit = 96): Collection
    {
        // Ignore args in fake; just return the prepared series
        return $this->ret;
    }
}
