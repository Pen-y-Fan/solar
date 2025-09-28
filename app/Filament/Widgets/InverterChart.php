<?php

namespace App\Filament\Widgets;

use App\Application\Queries\Energy\InverterConsumptionRangeQuery;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InverterChart extends ChartWidget
{
    protected static ?string $heading = 'Inverter consumption';

    protected static ?string $pollingInterval = '120s';

    public int $count = 1;

    protected function getData(): array
    {
        $data = $this->getQueryData();

        // Determine start and end range based on current time in London
        $endLondon = Carbon::now('Europe/London');
        $startLondon = $endLondon->copy()->subDay()->startOfDay();

        self::$heading = sprintf(
            'Consumption from %s to %s was %0.2f kWh',
            $startLondon->format('D jS M Y H:i'),
            $endLondon->format('jS M H:i'),
            $data->sum(fn ($item) => (float) $item['value'])
        );

        return [
            'datasets' => [
                [
                    'label' => 'Consumption',
                    'data' => $data->map(fn ($item): float => (float) $item['value']),
                    'fill' => true,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
                    'stepped' => 'middle',
                ],
            ],
            'labels' => $data->map(fn ($item): string => Carbon::createFromFormat('H:i:s', $item['time'], 'UTC')
                ->timezone('Europe/London')
                ->format('H:i')),
        ];
    }

    private function getQueryData(): Collection
    {
        $endUtc = Carbon::now('Europe/London')->timezone('UTC');
        $startUtc = Carbon::now('Europe/London')->subDay()->startOfDay()->timezone('UTC');

        /** @var InverterConsumptionRangeQuery $query */
        $query = app(InverterConsumptionRangeQuery::class);

        return $query->run($startUtc, $endUtc, 48);
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
