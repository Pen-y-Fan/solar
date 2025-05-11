<?php

namespace App\Filament\Widgets;

use App\Models\ActualForecast;
use App\Models\Inverter;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SolcastWithActualAndRealChart extends ChartWidget
{
    protected static ?string $heading = 'PV Yield vs Forecast solis actual Chart';

    protected static ?string $pollingInterval = '120s';

    public ?string $filter = '';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No data for PV Yield vs Forecast solis actual ';
            return [];
        }
        $this->setHeading($rawData);

        return [
            'datasets' => [
                [
                    'label' => 'Solcast actual',
                    'data' => $rawData->map(function ($item) {
                        return $item['pv_estimate'];
                    }),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)'
                ],
                [
                    'label' => 'PV Yield',
                    'data' => $rawData->map(function ($item) {
                        return $item['inverter_yield'];
                    }),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgb(255, 99, 132)'
                ],
            ],
            'labels' => $rawData->map(function ($item) {
                return Carbon::parse($item['period_end'], 'UTC')
                    ->timezone('Europe/London')
                    ->format('H:i');
            }),
        ];
    }

    protected function getFilters(): ?array
    {
        $periods = CarbonPeriod::create('now', '-1 day', 30);

        $data = [];
        foreach ($periods as $period) {
            $data[$period->format('Y-m-d')] = $period->format('D jS M');
        }

        return $data;
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function getDatabaseData(): \Illuminate\Support\Collection
    {

        $start = Carbon::parse($this->filter, 'Europe/London')->startOfDay();

        $limit = 48;

        // get actual forecast
        $actualForecast = ActualForecast::query()
            ->where('period_end', '>=', $start)
            ->orderBy('period_end')
            ->limit($limit)
            ->get();

        // get real data
        $inverterData = Inverter::query()
            ->where(
                'period',
                '>=',
                $start
            )
            ->orderBy('period')
            ->limit($limit)
            ->get();

        // return the combined data
        return $actualForecast->map(fn($item) => [
            'period_end' => $item->period_end,
            'pv_estimate' => $item->pv_estimate / 2,
            'inverter_yield' => $inverterData->where('period', '=', $item->period_end)
                    ->first()?->yield ?? 0
        ]);
    }

    private function setHeading(\Illuminate\Support\Collection $rawData): void
    {
        self::$heading = sprintf('Solis actual (%01.2f) vs PV Yield (%01.2f) Chart from %s to %s',
            $rawData->sum('pv_estimate'),
            $rawData->sum('inverter_yield'),
            Carbon::parse($rawData->first()['period_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            Carbon::parse($rawData->last()['period_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('jS M H:i'),
        );
    }
}
