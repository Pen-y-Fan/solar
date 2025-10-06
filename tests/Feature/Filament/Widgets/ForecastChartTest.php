<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Energy\Models\Inverter;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Unit\Filament\Widgets\ForecastChartTestWidget;

final class ForecastChartTest extends TestCase
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

    public function testForecastChartBuildsLabelsAndDatasetsFromSeededData(): void
    {
        // Seed a small set of half-hourly Forecasts for today (UTC) with matching Agile costs
        $start = now('Europe/London')->startOfDay()->timezone('UTC');
        $periods = [
            $start->copy()->addMinutes(0),
            $start->copy()->addMinutes(30),
            $start->copy()->addMinutes(60),
            $start->copy()->addMinutes(90),
        ];

        foreach ($periods as $i => $periodEnd) {
            /** @var Forecast $forecast */
            $forecast = Forecast::query()->create([
                'period_end' => $periodEnd,
                'pv_estimate' => 1.0 + $i,      // vary a little
                'pv_estimate10' => 0.8 + $i,
                'pv_estimate90' => 1.2 + $i,
            ]);

            // Import/Export costs matching the Forecast.period_end
            AgileImport::query()->create([
                'valid_from' => $forecast->period_end,
                'valid_to' => $forecast->period_end->copy()->addMinutes(30),
                'value_inc_vat' => 2.0 + $i,
                'value_exc_vat' => 1.5 + $i,
            ]);
            AgileExport::query()->create([
                'valid_from' => $forecast->period_end,
                'valid_to' => $forecast->period_end->copy()->addMinutes(30),
                'value_inc_vat' => 1.0 + ($i * 0.5),
                'value_exc_vat' => 0.8 + ($i * 0.5),
            ]);
        }

        // Seed Inverter averages for the same time slots within the last 21 days window
        // The widget groups by time(period), so we just need matching H:i:s times
        $yesterday = now('Europe/London')->subDay()->startOfDay()->timezone('UTC');
        foreach ($periods as $i => $periodEnd) {
            $time = $periodEnd->format('H:i:s');
            $period = $yesterday->copy()->setTimeFromTimeString($time);
            Inverter::query()->create([
                'period' => $period,
                'consumption' => 0.5 + ($i * 0.1),
                'yield' => 0.0,
                'to_grid' => 0.0,
                'from_grid' => 0.0,
                'battery_soc' => 50,
            ]);
        }

        // Use a testable shim to call protected methods directly
        $widget = app(ForecastChartTestWidget::class);
        $widget->setFilter('today');

        $data = $widget->callGetData();

        // Expect 4 datasets: Acc. grid import, Cost, Acc. Cost, Battery (%)
        $this->assertArrayHasKey('datasets', $data);
        $this->assertCount(4, $data['datasets']);

        // Labels should have one per period, formatted H:i (no strategy prefix for 'today')
        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(count($periods), $data['labels']);
        foreach ($periods as $idx => $p) {
            $this->assertSame(
                $p->timezone('Europe/London')->format('H:i'),
                (string) $data['labels'][$idx]
            );
        }

        // Ensure heading is set (i.e., not the 'No forecast data')
        // We can't access protected heading directly, but presence of datasets/labels implies non-empty state.
        $this->assertNotEmpty($data['datasets'][0]['data']);
    }
}
