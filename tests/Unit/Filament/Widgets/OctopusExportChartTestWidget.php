<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\OctopusExportChart;

/**
 * Testable OctopusExportChart subclass to expose protected methods for assertions.
 */
final class OctopusExportChartTestWidget extends OctopusExportChart
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
