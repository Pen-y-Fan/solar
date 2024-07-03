<?php

namespace App\Filament\Widgets;

use App\Actions\AgileExport as AgileExportAction;
use App\Actions\AgileImport as AgileImportAction;
use App\Actions\OctopusExport;
use App\Actions\OctopusImport;
use App\Models\AgileExport;
use App\Models\AgileImport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AgileChart extends ChartWidget
{
    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Agile forecast';
    protected static ?string $pollingInterval = '120s';

    /**
     * @var int|mixed
     */
    public float $minValue = 0.0;

    protected function getData(): array
    {
        $data = $this->getDatabaseData();

        self::$heading = sprintf('Agile costs from %s to %s',
            Carbon::parse($data->first()['valid_from'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            Carbon::parse($data->last()['valid_from'], 'UTC')
                ->timezone('Europe/London')
                ->format('jS M H:i'),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Export value',
                    'data' => $data->map(fn($item): string => $item['export_value_inc_vat']),
                    'fill' => true,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
                    'stepped' => 'middle',
                ],
                [
                    'label' => 'Import value',
                    'data' => $data->map(fn($item): string => $item['import_value_inc_vat']),
                    'fill' => '-1',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'stepped' => 'middle',
                ],
            ],
            'labels' => $data->map(function ($item): string {
                $date = Carbon::parse($item['valid_from'], 'UTC')
                    ->timezone('Europe/London');

                $format = $date->format('H:i') === '00:00' ? 'j M H:i' : 'H:i';

                return $date->format($format);
            }),
        ];
    }

    /*
        backgroundColor: [
          'rgba(255, 99, 132, 0.2)',
          'rgba(255, 159, 64, 0.2)',
          'rgba(255, 205, 86, 0.2)',
          'rgba(75, 192, 192, 0.2)', // green
          'rgba(54, 162, 235, 0.2)', // blue
          'rgba(153, 102, 255, 0.2)',
          'rgba(201, 203, 207, 0.2)'
        ],
        borderColor: [
          'rgb(255, 99, 132)', // red
          'rgb(255, 159, 64)', // orange
          'rgb(255, 205, 86)', // yellow
          'rgb(75, 192, 192)', // green
          'rgb(54, 162, 235)', // blue
          'rgb(153, 102, 255)', // purple
          'rgb(201, 203, 207)' // grey
        ],
     */

    private function getDatabaseData(): Collection
    {
        $limit = 96;
        $start = now()->timezone('Europe/London')->startOfDay()->timezone('UTC');

        $importData = AgileImport::query()
            ->where(
                'valid_from',
                '>=',
                $start
            )
            ->orderBy('valid_from')
            ->limit($limit)
            ->get();

        $exportData = AgileExport::query()
            ->where(
                'valid_from',
                '>=',
                $start
            )
            ->orderBy('valid_from')
            ->limit($limit)
            ->get();

        if (now()->diffInUTCHours($importData->last()->valid_to) < 7) {
            // Don't download if we have more than 7 hours of data from now, data is normally available after 4 PM.
            // We should have data up to 11 PM, 4 PM is 7 hours before 11pm.
            $this->updateAgileImport();
        }

        if (now()->diffInUTCHours($exportData->last()->valid_to) < 7) {
            // Don't download if we have more than 7 hours of data from now, data is normally available after 4 PM.
            // We should have data up to 11 PM, 4 PM is 7 hours before 11pm.
            $this->updateAgileExport();
        }

        // get min for value_inc_vat, including some space
        $min = floor($importData->min('value_inc_vat') ?? 1) - 1;
        // use a negative value or set to 0
        $this->minValue = min($min, 0);

        // combine the data for the chart
        return $importData->map(fn($item) => [
            'valid_from' => $item->valid_from,
            'import_value_inc_vat' => $item->value_inc_vat,
            'export_value_inc_vat' => $exportData->where('valid_from', '=', $item->valid_from)
                    ->first()?->value_inc_vat ?? 0
        ]);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'min' => $this->minValue,
                ]
            ]
        ];
    }

    private function updateAgileImport(): void
    {
        Log::info('Updating agile import chart data from API.');

        try {
            (new AgileImportAction())->run();
        } catch (Throwable $th) {
            Log::error('Error running Octopus Agile import action:', ['error message' => $th->getMessage()]);
        }

        try {
            (new OctopusImport())->run();
            Log::info('Octopus import has been fetched!');
        } catch (Throwable $th) {
            Log::error('Error running Octopus import action:', ['error message' => $th->getMessage()]);
        }
    }

    private function updateAgileExport(): void
    {
        Log::info('Updating agile export chart data from API.');

        try {
            (new AgileExportAction())->run();
        } catch (Throwable $th) {
            Log::error('Error running Octopus Agile export action:', ['error message' => $th->getMessage()]);
        }

        try {
            (new OctopusExport)->run();
        } catch (Throwable $th) {
            Log::error('Error running Octopus export action:', ['error message' => $th->getMessage()]);
        }
    }
}
