<?php

namespace App\Filament\Widgets;

use App\Models\Inverter;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class InverterChart extends ChartWidget
{
    protected static ?string $heading = 'Inverter consumption';

    protected static ?string $pollingInterval = '120s';

    public int $count = 1;

    protected function getData(): array
    {
        $data = $this->getDatabaseData();

        self::$heading = sprintf(
            'Consumption from %s to %s was %0.2f kWh',
            Carbon::parse($data->first()['period'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            Carbon::parse($data->last()['period'], 'UTC')
                ->timezone('Europe/London')
                ->format('jS M H:i'),
            $data->sum('consumption')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Consumption',
                    'data' => $data->map(fn ($item): string => $item['consumption']),
                    'fill' => true,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
                    'stepped' => 'middle',
                ],
            ],
            'labels' => $data->map(fn ($item): string => Carbon::parse($item['period'], 'UTC')
                ->timezone('Europe/London')
                ->format('H:i')),
        ];
    }

    private function getDatabaseData(): Collection
    {
        $lastExport = Inverter::query()
            ->latest('period')
            ->first() ?? now();

        return Inverter::query()
            ->where(
                'period',
                '>=',
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
                ],
            ],
        ];
    }
}
