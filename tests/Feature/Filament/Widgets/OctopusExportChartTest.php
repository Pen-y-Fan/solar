<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Energy\Actions\OctopusExport as OctopusExportAction;
use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\OctopusExport;
use App\Domain\User\Models\User;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Tests\Unit\Filament\Widgets\OctopusExportChartTestWidget;

final class OctopusExportChartTest extends TestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user);

        // Bind a fake action to avoid any external calls if invoked
        $fakeAction = new class {
            public function execute(): ActionResult
            {
                return ActionResult::success([], 'ok');
            }
        };
        $this->app->instance(OctopusExportAction::class, $fakeAction);
    }

    public function testOctopusExportChartBuildsDatasetsAndLabels(): void
    {
        // Seed two half-hour periods within the last 24 hours so no update is attempted
        $start = now()->subHours(2)->startOfHour();

        // Matching Agile export rates (value_inc_vat in pence)
        AgileExport::query()->create([
            'valid_from' => $start,
            'valid_to' => $start->clone()->addMinutes(30),
            'value_inc_vat' => 15.0,
        ]);
        AgileExport::query()->create([
            'valid_from' => $start->clone()->addMinutes(30),
            'valid_to' => $start->clone()->addMinutes(60),
            'value_inc_vat' => 12.0,
        ]);

        // Octopus export consumption (kWh) — note: widget negates for chart display
        OctopusExport::query()->create([
            'interval_start' => $start,
            'interval_end' => $start->clone()->addMinutes(30),
            'consumption' => 0.8,
            'updated_at' => now(),
        ]);
        OctopusExport::query()->create([
            'interval_start' => $start->clone()->addMinutes(30),
            'interval_end' => $start->clone()->addMinutes(60),
            'consumption' => 1.4,
            'updated_at' => now(),
        ]);

        /** @var OctopusExportChartTestWidget $widget */
        $widget = app(OctopusExportChartTestWidget::class);
        $data = $widget->callGetData();

        // Two datasets: Export (bar) and Accumulative cost (line)
        $this->assertArrayHasKey('datasets', $data);
        $this->assertCount(2, $data['datasets']);

        // Labels should be present for each point
        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(2, $data['labels']);

        // Export dataset values should be negative (widget negates consumption)
        $exportSeries = $data['datasets'][0]['data'];
        $this->assertLessThan(0, $exportSeries[0]);
        $this->assertLessThan(0, $exportSeries[1]);

        // Accumulative cost values should be negative (widget negates) and magnitude growing
        $costs = $data['datasets'][1]['data'];
        $this->assertLessThan(0, $costs[0]);
        $this->assertLessThan($costs[0], $costs[1]);
    }
}
