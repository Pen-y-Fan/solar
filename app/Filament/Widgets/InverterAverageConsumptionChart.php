<?php

namespace App\Filament\Widgets;

use App\Application\Queries\Energy\InverterConsumptionByTimeQuery;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InverterAverageConsumptionChart extends ChartWidget
{
    protected static ?string $pollingInterval = '120s';

    public int $count = 1;

    private \Illuminate\Support\Carbon $startDate;

    protected function getData(): array
    {
        $data = $this->getDatabaseData();

        if ($data->count() === 0) {
            self::$heading = 'No average consumption data';

            return [];
        }

        self::$heading = sprintf(
            'Average consumption since %s is %0.2f kWh',
            $this->startDate->format('D jS M Y'),
            $data->sum('value')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Average consumption',
                    'data' => $data->map(fn ($item): string => $item['value']),
                    'fill' => true,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'stepped' => 'middle',
                ],

            ],
            'labels' => $data->map(fn ($item): string => Str::take($item['time'], 5)),
        ];
    }

    private function getDatabaseData(): Collection
    {
        // Determine the start date for the average window (last 21 days from start of today in Europe/London)
        $this->startDate = now()->timezone('Europe/London')->startOfDay();

        /** @var InverterConsumptionByTimeQuery $query */
        $query = app(InverterConsumptionByTimeQuery::class);

        return $query->run($this->startDate);
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
