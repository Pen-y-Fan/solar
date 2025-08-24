<?php

namespace App\Domain\Energy\Imports;

use App\Domain\Energy\Models\Inverter;
use App\Domain\Energy\ValueObjects\BatteryStateOfCharge;
use App\Domain\Energy\ValueObjects\EnergyFlow;
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

                if (
                    ($lastInPeriod['Today Total Load Consumption(kWh)']
                        - $firstInPeriod['Today Total Load Consumption(kWh)']) < 0
                ) {
                    $lastInPeriod = $collection[$i - 2];
                }


                $energyFlow = new EnergyFlow(
                    yield: $lastInPeriod['Today Yield(kWh)'] - $firstInPeriod['Today Yield(kWh)'],
                    toGrid: $lastInPeriod['Total Energy to Grid(kWh)'] - $firstInPeriod['Total Energy to Grid(kWh)'],
                    fromGrid: $lastInPeriod['Total Energy from Grid(kWh)']
                    - $firstInPeriod['Total Energy from Grid(kWh)'],
                    consumption: max(
                        0.0,
                        $lastInPeriod['Today Total Load Consumption(kWh)']
                        - $firstInPeriod['Today Total Load Consumption(kWh)']
                    )
                );

                $batterySoc = isset($firstInPeriod['Battery SOC(%)'])
                    ? new BatteryStateOfCharge(percentage: (int)$firstInPeriod['Battery SOC(%)'])
                    : null;

                $data[] = [
                    'period'      => $currentPeriod->toDateTimeString(),
                    ...$energyFlow->toArray(),
                    'battery_soc' => $batterySoc?->percentage,
                ];

                $currentPeriod->addMinutes(30);
                $nextPeriod->addMinutes(30);

                $firstInPeriod = $row;
            }
        }
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
