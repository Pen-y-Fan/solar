<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\AgileChart;

/**
 * Testable AgileChart subclass to expose protected methods for assertions.
 */
final class AgileChartTestWidget extends AgileChart
{
    public function callGetData(): array
    {
        return $this->getData();
    }

    public function callGetOptions(): array
    {
        return $this->getOptions();
    }
}
