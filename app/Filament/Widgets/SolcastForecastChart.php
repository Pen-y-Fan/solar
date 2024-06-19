<?php

namespace App\Filament\Widgets;

use App\Models\Forecast;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SolcastForecastChart extends ChartWidget
{
    protected static ?string $heading = 'Solis Forecast';
    protected static ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        self::$heading = sprintf('Solis forecast for %s to %s',
            Carbon::parse($rawData->first()['period_end'], 'UTC')
                ->timezone(config('app.timezone'))
                ->format('d M H:i'),
            Carbon::parse($rawData->last()['period_end'], 'UTC')
                ->timezone(config('app.timezone'))
                ->format('d M Y H:i')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Forecast (10%)',
                    'data' => $rawData->map(fn($item): string => $item['pv_estimate10']),
                    'fill' => "+1",
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)'
                ],
                [
                    'label' => 'Forecast',
                    'data' => $rawData->map(fn($item): string => $item['pv_estimate']),
                    'fill' => "+1",
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgb(255, 99, 132)'
                ],
                [
                    'label' => 'Forecast (90%)',
                    'data' => $rawData->map(fn($item): string => $item['pv_estimate90']),
                ],
            ],
            'labels' => $rawData->map(fn($item): string => Carbon::parse($item['period_end'], 'UTC')
                ->timezone(config('app.timezone'))
                ->format('H:i')),
        ];
    }
    /*
        backgroundColor: [
          'rgba(255, 99, 132, 0.2)',
          'rgba(255, 159, 64, 0.2)',
          'rgba(255, 205, 86, 0.2)',
          'rgba(75, 192, 192, 0.2)',
          'rgba(54, 162, 235, 0.2)',
          'rgba(153, 102, 255, 0.2)',
          'rgba(201, 203, 207, 0.2)'
        ],
        borderColor: [
          'rgb(255, 99, 132)',
          'rgb(255, 159, 64)',
          'rgb(255, 205, 86)',
          'rgb(75, 192, 192)',
          'rgb(54, 162, 235)',
          'rgb(153, 102, 255)',
          'rgb(201, 203, 207)'
        ],
     */

    private function getDatabaseData()
    {
        $data = Forecast::where('period_end', '>=', now()->startOfHour())->orderBy('period_end')->limit(48)->get();
        // TODO: check if empty and call Action!
        return $data;
    }

    protected function getType(): string
    {
        return 'line';
    }
}
