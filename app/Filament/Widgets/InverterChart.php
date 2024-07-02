<?php

namespace App\Filament\Widgets;

use App\Models\Inverter;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class InverterChart extends ChartWidget
{
    protected static ?string $heading = 'Inverter yield';

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = '10s';

    public int $count = 1;

    protected function getData(): array
    {
        $data = $this->getDatabaseData();

        self::$heading = sprintf('Inverter consumption from %s to %s',
            Carbon::parse($data->first()['period'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M H:i'),
            Carbon::parse($data->last()['period'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M Y H:i')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Consumption',
                    'data' => $data->map(fn($item): string => $item['consumption']),
                    'fill' => true,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
                    'stepped' => 'middle',
                ],

            ],
            'labels' => $data->map(fn($item): string => Carbon::parse($item['period'], 'UTC')
                ->timezone('Europe/London')
                ->format('j M H:i'))
        ];
    }

    private function getDatabaseData()
    {
        $lastExport = Inverter::query()
            ->latest('period')
            ->first() ?? now();

        return Inverter::query()
            ->where(
                'period', '>=',
                // possibly use a sub query to get the last interval and sub 1 day
                $lastExport->period->timezone('Europe/London')->subDay()
                    ->startOfDay()->timezone('UTC')
            )
            ->orderBy('period')
            ->limit(48)
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
