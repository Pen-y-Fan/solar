<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Energy\Actions\OctopusImport as OctopusImportAction;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Energy\Models\OctopusImport;
use App\Domain\User\Models\User;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Tests\Unit\Filament\Widgets\OctopusImportChartTestWidget;

final class OctopusImportChartTest extends TestCase
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
        $this->app->instance(OctopusImportAction::class, $fakeAction);
    }

    public function testOctopusImportChartBuildsDatasetsAndLabels(): void
    {
        // Seed two half-hour periods within the last 24 hours so no update is attempted
        $start = now()->subHours(2)->startOfHour();

        // Matching Agile import rates (value_inc_vat in pence)
        AgileImport::query()->create([
            'valid_from' => $start,
            'valid_to' => $start->clone()->addMinutes(30),
            'value_inc_vat' => 20.0,
        ]);
        AgileImport::query()->create([
            'valid_from' => $start->clone()->addMinutes(30),
            'valid_to' => $start->clone()->addMinutes(60),
            'value_inc_vat' => 25.0,
        ]);

        // Octopus import consumption (kWh)
        OctopusImport::query()->create([
            'interval_start' => $start,
            'interval_end' => $start->clone()->addMinutes(30),
            'consumption' => 0.5,
            'updated_at' => now(),
        ]);
        OctopusImport::query()->create([
            'interval_start' => $start->clone()->addMinutes(30),
            'interval_end' => $start->clone()->addMinutes(60),
            'consumption' => 1.25,
            'updated_at' => now(),
        ]);

        // Use the shim to call protected methods
        /** @var OctopusImportChartTestWidget $widget */
        $widget = app(OctopusImportChartTestWidget::class);
        $data = $widget->callGetData();

        // We expect two datasets: Usage (bar) and Accumulative cost (line)
        $this->assertArrayHasKey('datasets', $data);
        $this->assertCount(2, $data['datasets']);

        // Labels should be present for each point (formatted H:i Europe/London)
        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(2, $data['labels']);

        // Accumulative cost should be positive and growing (pence->pounds conversion happens in widget)
        $costs = $data['datasets'][1]['data'];
        $this->assertGreaterThan(0, $costs[0]);
        $this->assertGreaterThan($costs[0], $costs[1]);
    }
}
