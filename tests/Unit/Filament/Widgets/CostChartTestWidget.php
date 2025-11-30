<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Resources\StrategyResource\Widgets\CostChart;

/**
 * Testable CostChart subclass to expose protected methods for assertions.
 */
final class CostChartTestWidget extends CostChart
{
    public function callGetOptions(): array
    {
        return $this->getOptions();
    }
}
