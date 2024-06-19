<?php

namespace App\Filament\Widgets;

use App\Models\AgileExport;
use App\Models\AgileImport;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AgileChart extends ChartWidget
{
    protected int|string|array $columnSpan = 2;

    protected static ?string $heading = 'Agile forecast';
    protected static ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $data = $this->getDatabaseData();

        self::$heading = sprintf('Agile costs from %s to %s',
            Carbon::parse($data->first()['valid_from'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M H:i'),
            Carbon::parse($data->last()['valid_from'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M Y H:i')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Export value',
                    'data' => $data->map(fn($item): string => $item['export_value_inc_vat']),
                    'fill' => "-1",
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
                    'stepped' => 'middle',
                ],
                [
                    'label' => 'Import value',
                    'data' => $data->map(fn($item): string => $item['import_value_inc_vat']),
                    'fill' => "-1",
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'stepped' => 'middle',
                ],
            ],
            'labels' => $data->map(fn($item): string => Carbon::parse($item['valid_from'], 'UTC')
                ->timezone('Europe/London')
                ->format('j M H:i')),
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
          'rgb(255, 99, 132)',
          'rgb(255, 159, 64)',
          'rgb(255, 205, 86)',
          'rgb(75, 192, 192)', // green
          'rgb(54, 162, 235)', // blue
          'rgb(153, 102, 255)',
          'rgb(201, 203, 207)'
        ],
     */

    private function getDatabaseData()
    {
        $start = now()->timezone('Europe/London')->startOfDay()->timezone('UTC');
        $limit = 96;
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
        // TODO: call action after 4 PM the same date the data expires

        return $importData->map(fn($item) => [
            'valid_from' => $item->valid_from,
            'import_value_inc_vat' => $item->value_inc_vat,
            'export_value_inc_vat' => $exportData->where('valid_from', '=', $item->valid_from)->first()?->value_inc_vat ?? 0
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
                    'min' => 0,
                ]
            ]
        ];
    }
}
