<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Queries\Energy\AgileImportExportSeriesQuery;
use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Unit\Filament\Widgets\AgileChartTestWidget;
use Tests\Unit\Filament\Widgets\FakeAgileImportExportSeriesQuery;

final class AgileChartTest extends TestCase
{
    use DatabaseMigrations;

    public function testAgileChartBuildsSeriesAndTimeWindowLabels(): void
    {
        // Authenticate (Filament often expects an authenticated context)
        $user = User::factory()->create();
        $this->actingAs($user);

        // Seed recent-enough import/export rows so the widget doesn't attempt API updates
        // Ensure diffInUTCHours >= 7
        $ts = now()->addHours(8)->startOfHour();

        AgileImport::query()->create([
            'valid_from' => $ts,
            'valid_to' => $ts->clone()->addMinutes(30),
            'value_inc_vat' => 1.23,
        ]);
        AgileExport::query()->create([
            'valid_from' => $ts,
            'valid_to' => $ts->clone()->addMinutes(30),
            'value_inc_vat' => 0.99,
        ]);

        // Prepare a deterministic series including a midnight point and a negative import to exercise y-min logic
        $series = new Collection([
            [
                'valid_from' => '2025-01-01 00:00:00', // midnight UTC => midnight Europe/London in Jan
                'import_value_inc_vat' => -1.2,
                'export_value_inc_vat' => 0.5,
            ],
            [
                'valid_from' => '2025-01-01 00:30:00',
                'import_value_inc_vat' => 2.0,
                'export_value_inc_vat' => 1.0,
            ],
            [
                'valid_from' => '2025-01-01 01:00:00',
                'import_value_inc_vat' => 3.5,
                'export_value_inc_vat' => 1.2,
            ],
        ]);

        // Bind fake query so the widget uses it via the container
        $this->app->instance(AgileImportExportSeriesQuery::class, new FakeAgileImportExportSeriesQuery($series));

        // Use a testable widget shim to call protected methods
        $widget = app(AgileChartTestWidget::class);
        $data = $widget->callGetData();

        // Assert datasets (Export, Import, Avg Export, Avg Import)
        $this->assertArrayHasKey('datasets', $data);
        $this->assertCount(4, $data['datasets']);
        // Labels should match number of series points
        $this->assertArrayHasKey('labels', $data);
        $this->assertCount($series->count(), $data['labels']);

        // Label formatting: midnight should include date (j M H:i), subsequent label is just time (H:i)
        $this->assertStringContainsString('1 Jan 00:00', (string) $data['labels'][0]);
        $this->assertSame('00:30', $data['labels'][1]);

        // Options should reflect y-axis min snapped to a multiple of 5 below the min negative value
        // min import = -1.2 => floor(-1.2) = -2 => floor(-2/5)*5 = -5
        $options = $widget->callGetOptions();
        $this->assertSame(-5.0, $options['scales']['y']['min']);
    }
}
