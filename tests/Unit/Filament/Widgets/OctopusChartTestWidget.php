<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\OctopusChart;

/**
 * Testable OctopusChart subclass to expose protected methods for assertions.
 */
final class OctopusChartTestWidget extends OctopusChart
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
