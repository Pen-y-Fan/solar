<?php

namespace App\Filament\Widgets;

use App\Domain\Forecasting\Models\ActualForecast;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SolcastWithActualAndRealChart extends ChartWidget
{
    protected static ?string $heading = 'PV Yield vs Forecast Solcast actual forecast chart';

    protected static ?string $pollingInterval = '120s';

    public ?string $filter = '';

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No data for PV yield vs Solcast actual forecast';

            return [];
        }
        $this->setHeading($rawData);

        return [
            'datasets' => [
                [
                    'label'           => 'Solcast actual',
                    'data'            => $rawData->map(function ($item) {
                        return $item['pv_estimate'];
                    }),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor'     => 'rgb(75, 192, 192)',
                ],
                [
                    'label'           => 'PV Yield',
                    'data'            => $rawData->map(function ($item) {
                        return $item['inverter_yield'];
                    }),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor'     => 'rgb(255, 99, 132)',
                ],
            ],
            'labels'   => $rawData->map(function ($item) {
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

    private function getDatabaseData(): Collection
    {

        $start = Carbon::parse($this->filter, 'Europe/London')->startOfDay();

        $limit = 48;

        $actualForecasts = ActualForecast::query()
            ->with(['inverter:id,yield,period'])
            ->where('period_end', '>=', $start)
            ->orderBy('period_end')
            ->limit($limit)
            ->get(['id', 'period_end', 'pv_estimate']);

        return $actualForecasts->map(fn(ActualForecast $actualForecast) => [
            'period_end'     => $actualForecast->period_end,
            'pv_estimate'    => $actualForecast->pv_estimate / 2,
            'inverter_yield' => $actualForecast->inverter?->yield,
        ]);
    }

    private function setHeading(Collection $rawData): void
    {
        self::$heading = sprintf(
            'Solcast actual (%01.2f) vs PV Yield (%01.2f) Chart from %s to %s',
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
