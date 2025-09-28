<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Queries;

use App\Application\Queries\Energy\ElectricImportExportSeriesQuery;
use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Energy\Models\Inverter;
use App\Domain\Energy\Models\OctopusExport;
use App\Domain\Energy\Models\OctopusImport;
use App\Domain\Strategy\Models\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

final class ElectricImportExportSeriesQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testReturnsSeriesWithAccumulationAndOrdering(): void
    {
        $start = now('UTC')->startOfDay();

        // Create two 30-min slots
        // Slot 1
        OctopusExport::query()->create([
            'interval_start' => $start->copy()->addMinutes(0),
            'interval_end' => $start->copy()->addMinutes(30),
            'consumption' => 2.0, // export consumption (kWh)
        ]);
        // Associated rate and import data for slot 1
        Strategy::query()->create([
            'period' => $start->copy()->addMinutes(0),
            'export_value_inc_vat' => 10.0, // 10p per kWh
        ]);
        AgileImport::query()->create([
            'valid_from' => $start->copy()->addMinutes(0),
            'value_inc_vat' => 20.0, // 20p per kWh
        ]);
        AgileExport::query()->create([
            'valid_from' => $start->copy()->addMinutes(0),
            'value_inc_vat' => 10.0,
        ]);
        OctopusImport::query()->create([
            'interval_start' => $start->copy()->addMinutes(0),
            'consumption' => 1.0, // kWh
        ]);
        Inverter::query()->create([
            'period' => $start->copy()->addMinutes(0),
            'battery_soc' => 50.0,
        ]);

        // Slot 2
        OctopusExport::query()->create([
            'interval_start' => $start->copy()->addMinutes(30),
            'interval_end' => $start->copy()->addMinutes(60),
            'consumption' => 1.0,
        ]);
        Strategy::query()->create([
            'period' => $start->copy()->addMinutes(30),
            'export_value_inc_vat' => 20.0, // 20p per kWh
        ]);
        AgileImport::query()->create([
            'valid_from' => $start->copy()->addMinutes(30),
            'value_inc_vat' => 40.0, // 40p per kWh
        ]);
        AgileExport::query()->create([
            'valid_from' => $start->copy()->addMinutes(30),
            'value_inc_vat' => 20.0,
        ]);
        OctopusImport::query()->create([
            'interval_start' => $start->copy()->addMinutes(30),
            'consumption' => 3.0,
        ]);
        Inverter::query()->create([
            'period' => $start->copy()->addMinutes(30),
            'battery_soc' => 60.0,
        ]);

        /** @var ElectricImportExportSeriesQuery $query */
        $query = App::make(ElectricImportExportSeriesQuery::class);
        $result = $query->run($start, 48);

        $this->assertCount(2, $result);

        $first = $result->first();
        /** @var array<string, mixed> $first */
        $this->assertSame((string)$start->copy()->addMinutes(0), $first['interval_start']);
        $this->assertEqualsWithDelta(2.0, $first['export_consumption'], 0.001);
        $this->assertEqualsWithDelta(1.0, $first['import_consumption'], 0.001);
        // Costs: export 2kWh * 10p = £0.20; import 1kWh * -20p = -£0.20
        $this->assertEqualsWithDelta(0.20, $first['export_cost'], 0.001);
        $this->assertEqualsWithDelta(-0.20, $first['import_cost'], 0.001);
        $this->assertEqualsWithDelta(0.20, $first['export_accumulative_cost'], 0.001);
        $this->assertEqualsWithDelta(-0.20, $first['import_accumulative_cost'], 0.001);
        $this->assertEqualsWithDelta(0.00, $first['net_accumulative_cost'], 0.001);
        $this->assertEqualsWithDelta(50.0, $first['battery_percent'], 0.001);

        $second = $result->last();
        /** @var array<string, mixed> $second */
        $this->assertSame((string)$start->copy()->addMinutes(30), $second['interval_start']);
        $this->assertEqualsWithDelta(1.0, $second['export_consumption'], 0.001);
        $this->assertEqualsWithDelta(3.0, $second['import_consumption'], 0.001);
        // Costs: export £0.20 + (1kWh*20p=£0.20)=£0.40; import -£0.20 + (3kWh*-40p = -£1.20) = -£1.40
        $this->assertEqualsWithDelta(0.40, $second['export_accumulative_cost'], 0.001);
        $this->assertEqualsWithDelta(-1.40, $second['import_accumulative_cost'], 0.001);
        $this->assertEqualsWithDelta(-1.00, $second['net_accumulative_cost'], 0.001);
        $this->assertEqualsWithDelta(60.0, $second['battery_percent'], 0.001);
    }
}
