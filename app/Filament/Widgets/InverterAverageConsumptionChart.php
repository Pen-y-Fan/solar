<?php

namespace App\Filament\Widgets;

use App\Models\Inverter;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class InverterAverageConsumptionChart extends ChartWidget
{
    protected int|string|array $columnSpan = 1;
    protected static ?string $pollingInterval = '20s';

    public int $count = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Average consumption (Inverter) from ' . now()->timezone('Europe/London')->subdays(10)
                ->startOfDay()
                ->diffForHumans(parts: 2);
    }

    protected function getData(): array
    {
        $data = $this->getDatabaseData();

        return [
            'datasets' => [
                [
                    'label' => 'Average consumption',
                    'data' => $data->map(fn($item): string => $item['value']),
                    'fill' => true,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'stepped' => 'middle',
                ],

            ],
            'labels' => $data->map(fn($item): string => $item['time'])
        ];
    }

    private function getDatabaseData()
    {
        return Inverter::query()
            ->select(DB::raw('time(period) as `time`, avg(`consumption`) as `value`'))
            ->where(
                'period',
                '>',
                now()->timezone('Europe/London')->subdays(10)
                ->startOfDay()
                ->timezone('UTC')
            )
            ->groupBy('time')
            ->get();
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
