<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\OctopusImportChart;

/**
 * Testable OctopusImportChart subclass to expose protected methods for assertions.
 */
final class OctopusImportChartTestWidget extends OctopusImportChart
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
