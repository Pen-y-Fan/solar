<?php

namespace Tests\Feature\Imports;

use App\Imports\InverterImport;
use App\Models\Inverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class InverterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_inverter_import_can_create_inverter_data(): void
    {
        Excel::import(
            new InverterImport(),
            'tests/Inverter Test History Report_20240625044020_1300386381676952160.xls',
            null,
            \Maatwebsite\Excel\Excel::XLS
        );
        // Data from 2024-06-22, yield 17.6, to grid 8.4, imported 1.8, consumption 10.9

        $inverters = Inverter::all();

        $this->assertEqualsWithDelta(17.6, $inverters->sum('yield'), 0.001 );
        $this->assertEqualsWithDelta(8.4, $inverters->sum('to_grid'), 0.001 );
        $this->assertEqualsWithDelta(10.9, $inverters->sum('consumption'), 0.001 );
        $this->assertEqualsWithDelta(1.8 , $inverters->sum('from_grid'), 0.001 );
    }

    public function test_a_inverter_can_be_upserted_for_the_same_period(): void
    {
        Excel::import(
            new InverterImport(),
               'tests/Inverter Test History Report_20240625044020_1300386381676952160.xls',
            null,
            \Maatwebsite\Excel\Excel::XLS
        );

        $this->assertDatabaseCount('inverters', 48);

        Excel::import(
            new InverterImport(),
            'tests/Inverter Test History Report_20240625044020_1300386381676952160.xls',
            null,
            \Maatwebsite\Excel\Excel::XLS
        );

        $this->assertDatabaseCount('inverters', 48);
    }
}
