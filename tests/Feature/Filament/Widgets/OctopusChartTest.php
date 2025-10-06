<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Energy\Models\OctopusExport;
use App\Domain\Energy\Models\OctopusImport;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Tests\Unit\Filament\Widgets\OctopusChartTestWidget;

final class OctopusChartTest extends TestCase
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
    }

    public function testOctopusChartBuildsBaseDatasetsAndLabels(): void
    {
        // Seed two half-hour periods within the last few hours to avoid any background updates
        $start = now()->subHours(2)->startOfHour();

        // Matching Agile export/import rates (value_inc_vat in pence)
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

        // Exports (kWh)
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

        // Matching imports (kWh)
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

        /** @var OctopusChartTestWidget $widget */
        $widget = app(OctopusChartTestWidget::class);

        // Ensure filter targets the seeded day explicitly to avoid defaulting to a different date
        $widget->filter = $start->timezone('Europe/London')->format('Y-m-d');

        $data = $widget->callGetData();

        // Five datasets expected: Export, Export accumulative cost, Import,
        // Import accumulative cost, Net accumulative cost
        $this->assertArrayHasKey('datasets', $data);
        $this->assertCount(5, $data['datasets']);

        // Labels should be present and match the number of points
        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(2, $data['labels']);

        // Export series should be negative (widget negates consumption for display)
        $exportSeries = $data['datasets'][0]['data'];
        $this->assertLessThan(0, $exportSeries[0]);
        $this->assertLessThan(0, $exportSeries[1]);

        // Import series should be positive (raw consumption)
        $importSeries = $data['datasets'][2]['data'];
        $this->assertGreaterThan(0, $importSeries[0]);
        $this->assertGreaterThan(0, $importSeries[1]);

        // Export accumulative cost (negated) should be negative and grow in magnitude
        $exportCostSeries = $data['datasets'][1]['data'];
        $this->assertLessThan(0, $exportCostSeries[0]);
        $this->assertLessThan($exportCostSeries[0], $exportCostSeries[1]);

        // Import accumulative cost (negated of negative) should be positive and growing
        $importCostSeries = $data['datasets'][3]['data'];
        $this->assertGreaterThan(0, $importCostSeries[0]);
        $this->assertGreaterThan($importCostSeries[0], $importCostSeries[1]);
    }
}
