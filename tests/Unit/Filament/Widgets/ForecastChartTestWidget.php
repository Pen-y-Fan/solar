<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\ForecastChart;

/**
 * Testable ForecastChart subclass to expose protected methods for assertions.
 */
final class ForecastChartTestWidget extends ForecastChart
{
    public function callGetData(): array
    {
        return $this->getData();
    }

    public function callGetOptions(): array
    {
        return $this->getOptions();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }
}
