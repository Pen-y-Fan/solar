<?php

namespace App\Imports;

use App\Models\Inverter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

/**
 * Row 8 is the heading
 *
 * Time [Column B] -> Period - converted to 30 min time periods
 *
 * Today Yield(kWh) -> yield - PV yield for the period
 *
 * Column 'Daily Energy to Grid(kWh)' has been removed from Excel sheet
 * ~~Daily Energy to Grid(kWh) -> to_grid - Excess energy send to the grid for the period~~
 * Total Energy to Grid(kWh) -> accumulated energy sent to the grid
 *
 * Column 'Daily Energy from Grid(kWh)' has been removed from Excel sheet
 * ~~Daily Energy from Grid(kWh) = from_grid - Import energy imported from the grid~~
 *
 * Total Energy from Grid(kWh) -> accumulated energy imported from the grid
 *
 * Today Total Load Consumption(kWh) = consumption - Energy consumed during the period
 *
 * (new InverterImport)->import('inverter.xls', null, \Maatwebsite\Excel\Excel::XLS);
 * $collection = (new InverterImport)->toCollection('inverter.xlsx');
 * Possibly:
 * $collection = (new InverterImport)->toCollection('inverter.xlsx', null, \Maatwebsite\Excel\Excel::XLS);
 */
class InverterImport implements ToCollection, WithHeadingRow
{
    public function __construct()
    {
        HeadingRowFormatter::default(HeadingRowFormatter::FORMATTER_NONE);
    }

    public function collection(Collection $collection)
    {
        if ($collection->isEmpty()) {
            return;
        }

        // 0 index
        $collectionCount = $collection->count() - 1;

        $firstInPeriod = $collection[0];

        $dateTimeWithoutTZ = substr($collection[0]['Time'], 0, 19);
        $carbonInstance = Carbon::createFromFormat('d/m/Y H:i:s', $dateTimeWithoutTZ, 'UTC');
        assert($carbonInstance instanceof Carbon);
        $currentPeriod = $carbonInstance->timezone('UTC')->startOfHour();
        $nextPeriod = $currentPeriod->clone()->addMinutes(30);

        $data = [];
        foreach ($collection as $i => $row) {

            $dateTimeWithoutTZ = substr($row['Time'], 0, 19); // '23/06/2024 00:00:10'
            $carbonInstance = Carbon::createFromFormat('d/m/Y H:i:s', $dateTimeWithoutTZ, 'UTC');
            assert($carbonInstance instanceof Carbon);

            if ($carbonInstance > $nextPeriod || $i === $collectionCount) {

                $lastInPeriod = $row;

                // Sometimes the last row of data for the day is reset to 0 prematurely in the export report.
                // the difference for the day will be negative.
                if (($lastInPeriod['Today Total Load Consumption(kWh)'] - $firstInPeriod['Today Total Load Consumption(kWh)']) < 0) {
                    // use the previous row of data
                    $lastInPeriod = $collection[$i - 2];
                }

                $data[] = [
                    'period' => $currentPeriod->toDateTimeString(),
                    'yield' => $lastInPeriod['Today Yield(kWh)'] - $firstInPeriod['Today Yield(kWh)'],
                    // Column 'Daily Energy to Grid(kWh)' has been removed from Excel sheet
                    // 'to_grid' => $lastInPeriod['Daily Energy to Grid(kWh)'] - $firstInPeriod['Daily Energy to Grid(kWh)'],
                    'to_grid' => $lastInPeriod['Total Energy to Grid(kWh)'] - $firstInPeriod['Total Energy to Grid(kWh)'],
                    // Column 'Daily Energy from Grid(kWh)' has been removed from Excel sheet
                    // 'from_grid' => $lastInPeriod['Daily Energy from Grid(kWh)'] - $firstInPeriod['Daily Energy from Grid(kWh)'],
                    'from_grid' => $lastInPeriod['Total Energy from Grid(kWh)'] - $firstInPeriod['Total Energy from Grid(kWh)'],
                    'battery_soc' => $firstInPeriod['Battery SOC(%)'],
                    'consumption' => $lastInPeriod['Today Total Load Consumption(kWh)'] - $firstInPeriod['Today Total Load Consumption(kWh)'],
                ];
                // Log::info('First in period', ['index' => $i, 'Last in period' => $lastInPeriod['Daily Energy to Grid(kWh)'], 'First in period' => $firstInPeriod['Daily Energy to Grid(kWh)']]);

                $currentPeriod->addMinutes(30);
                $nextPeriod->addMinutes(30);

                $firstInPeriod = $row;
            }

            // this is a test implementation, the final one would aggregate the usage for the 30 min and add it to the
            // start time for that period

        }
        // Log::info('Saving excel', ['data' => $data]);
        Inverter::upsert(
            $data,
            uniqueBy: ['period'],
            update: ['yield', 'to_grid', 'from_grid', 'consumption', 'battery_soc']
        );
    }

    public function headingRow(): int
    {
        return 8;
    }
}
