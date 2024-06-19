<?php

namespace App\Filament\Widgets;

use App\Models\ActualForecast;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;


class SolcastActualChart extends ChartWidget
{
    protected static ?string $heading = 'Solcast actual';
    protected static ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if($rawData->count() === 0) {
            self::$heading = 'No data for Solis actual';
            return [];
        }

        $label = sprintf('actual from %s to %s',
            Carbon::parse($rawData->first()['period_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M H:i'),
            Carbon::parse($rawData->last()['period_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M Y H:i')
        );

        self::$heading = 'Solcast ' . $label;

        $label = str($label)->ucfirst();

        return [
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $rawData->map(function ($item) {
                        return $item['pv_estimate'];
                    }),
                ],
            ],
            'labels' => $rawData->map(function ($item) {
                return Carbon::parse($item['period_end'], 'UTC')
                    ->timezone('Europe/London')
                    ->format('H:i');
            }),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function getDatabaseData()
    {
        $data = ActualForecast::where('period_end', '>=', now()->startOfHour()->subDay())->orderBy('period_end')->limit(48)->get();
        // TODO: check if empty and call Action!
        return $data;
    }
}
