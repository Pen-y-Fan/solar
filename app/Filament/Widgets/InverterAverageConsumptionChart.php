<?php

namespace App\Filament\Widgets;

use App\Models\Inverter;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InverterAverageConsumptionChart extends ChartWidget
{
    protected static ?string $pollingInterval = '120s';

    public int $count = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Average consumption (Inverter) since ' . now()->timezone('Europe/London')->subdays(10)
                ->startOfDay()
                ->format('D jS M Y');
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
            'labels' => $data->map(fn($item): string => Str::take($item['time'], 5))
        ];
    }

    private function getDatabaseData(): Collection|array
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
