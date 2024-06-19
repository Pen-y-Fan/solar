<?php

namespace App\Filament\Widgets;

use App\Models\OctopusImport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OctopusImportChart extends ChartWidget
{
    protected static ?string $heading = 'Electricity import';
    protected static ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No electric usage data';
            return [];
        }

        $label = sprintf('usage from %s to %s',
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
        $lastImport = OctopusImport::query()
            ->latest('interval_start')
            ->first() ?? now();

        $data = OctopusImport::query()
            ->where(
                'interval_start', '>=',
                // possibly use a sub query to get the last interval and sub 1 day
                $lastImport->interval_start->timezone('Europe/London')->subDay()
                    ->startOfDay()->timezone('UTC')
            )
            ->orderBy('interval_start')
            ->limit(48)
            ->get();

        // TODO: check if empty and call Action!
        return $data;
    }
}
