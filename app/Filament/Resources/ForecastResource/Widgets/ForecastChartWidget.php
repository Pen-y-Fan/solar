<?php

namespace App\Filament\Resources\ForecastResource\Widgets;

use App\Models\Forecast;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class ForecastChartWidget extends ChartWidget
{
    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Forecast';

    protected static ?string $pollingInterval = '120s';

    public function getData(): array
    {
        $startOfDay = Carbon::now()->startOfDay();
        $endOfDay = Carbon::now()->addDays(3)->endOfDay();

        $forecasts = Forecast::query()
            ->whereBetween('period_end', [$startOfDay, $endOfDay])
            ->orderBy('period_end')
            ->get(['period_end', 'pv_estimate', 'pv_estimate10', 'pv_estimate90']);

        $labels = $forecasts->pluck('period_end')->map(fn ($date) => $date->format('D jS M Y H:i'));
        $pvEstimates = $forecasts->pluck('pv_estimate');
        $pvEstimate10s = $forecasts->pluck('pv_estimate10');
        $pvEstimate90s = $forecasts->pluck('pv_estimate90');

        return [
            'datasets' => [
                [
                    'label' => 'Forecast (10%)',
                    'data' => $pvEstimate10s,
                    'fill' => '+1',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
                ],
                [
                    'label' => 'Forecast',
                    'data' => $pvEstimates,
                    'fill' => '+1',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgb(255, 99, 132)',
                ],
                [
                    'label' => 'Forecast (90%)',
                    'data' => $pvEstimate90s,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
