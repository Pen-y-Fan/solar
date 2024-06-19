<?php

namespace App\Filament\Widgets;

use App\Models\OctopusExport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OctopusExportChart extends ChartWidget
{
    protected static ?string $heading = 'Electricity export';
    protected static ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No electric export data';
            return [];
        }

        $label = sprintf('export from %s to %s',
            Carbon::parse($rawData->first()['interval_start'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M H:i'),
            Carbon::parse($rawData->last()['interval_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M Y H:i')
        );

        self::$heading = 'Electric ' . $label;

        $label = str($label)->ucfirst();

        return [
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $rawData->map(function ($item) {
                        return $item['consumption'];
                    }),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)'
                ],
            ],
            'labels' => $rawData->map(function ($item) {
                return Carbon::parse($item['interval_start'], 'UTC')
                    ->timezone('Europe/London')
                    ->format('H:i');
            }),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function getDatabaseData()
    {
        $lastExport = OctopusExport::query()
            ->latest('interval_start')
            ->first() ?? now();

        $data = OctopusExport::query()
            ->where(
                'interval_start', '>=',
                // possibly use a sub query to get the last interval and sub 1 day
                $lastExport->interval_start->timezone('Europe/London')->subDay()
                    ->startOfDay()->timezone('UTC')
            )
            ->orderBy('interval_start')
            ->limit(48)
            ->get();

        // TODO: check if empty and call Action!
        return $data;
    }
}
