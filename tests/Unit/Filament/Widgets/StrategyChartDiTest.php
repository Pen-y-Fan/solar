<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Application\Queries\Strategy\StrategyManualSeriesQuery;
use App\Domain\Strategy\Models\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class StrategyChartDiTest extends TestCase
{
    public function testStrategyChartUsesStrategyManualSeriesQuery(): void
    {
        // Prepare a fake set of Strategy models (can be unsaved for unit test)
        /** @var Collection<int, Strategy> $strategies */
        $strategies = collect([
            new Strategy(['period' => '2025-01-01']),
            new Strategy(['period' => '2025-01-02']),
        ]);

        // Prepare a fake query object (cannot mock final classes easily)
        $returnSeries = collect([
            [
                'period_end' => CarbonImmutable::parse('2025-01-01 00:30', 'UTC'),
                'import' => 1.0,
                'export' => 0.5,
                'acc_cost' => 0.10,
                'import_accumulative_cost' => 0.10,
                'export_accumulative_cost' => 0.00,
                'battery_percent' => 50,
                'charging' => false,
            ],
            [
                'period_end' => CarbonImmutable::parse('2025-01-01 01:00', 'UTC'),
                'import' => 0.0,
                'export' => 0.8,
                'acc_cost' => 0.05,
                'import_accumulative_cost' => 0.10,
                'export_accumulative_cost' => 0.05,
                'battery_percent' => 55,
                'charging' => true,
            ],
        ]);

        $fakeQuery = new FakeStrategyManualSeriesQuery(collect($returnSeries->all()), $strategies);

        // Bind the fake into the container so StrategyChart resolves it via app(...)
        $this->app->instance(StrategyManualSeriesQuery::class, $fakeQuery);

        // Create a testable widget that returns our strategies from the page table
        $widget = new StrategyChartTestWidget($strategies);

        $data = $widget->callGetData();

        // Basic assertions on structure based on our mocked query data
        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
        // 6 datasets expected: Import, Export, Acc. Cost, Acc. import cost, Acc. export cost, Battery
        $this->assertCount(6, $data['datasets']);
        $this->assertCount(2, $data['labels']);
    }
}
