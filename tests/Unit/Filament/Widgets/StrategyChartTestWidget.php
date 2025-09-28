<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Resources\StrategyResource\Widgets\StrategyChart;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Testable StrategyChart subclass that supplies table records and exposes getData().
 */
final class StrategyChartTestWidget extends StrategyChart
{
    public function __construct(private Collection $strategies)
    {
    }

    protected function getPageTableRecords(): EloquentCollection
    {
        return new EloquentCollection($this->strategies->all());
    }

    public function callGetData(): array
    {
        return $this->getData();
    }
}
