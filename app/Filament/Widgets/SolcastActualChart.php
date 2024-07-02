<?php

namespace App\Filament\Widgets;

use App\Actions\ActualForecast as ActualForecastAction;
use App\Models\ActualForecast;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;


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
        $lastRecord = $rawData->last();

        $label = sprintf('actual from %s to %s (last updated %s)',
            Carbon::parse($rawData->first()['period_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M H:i'),
            Carbon::parse($lastRecord['period_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('d M Y H:i'),
            Carbon::parse($lastRecord['updated_at'], 'UTC')
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

    private function getDatabaseData(): Collection
    {
        $data = ActualForecast::query()
        ->where('period_end', '>=', now()->startOfHour()->subDay())->orderBy('period_end')
            ->limit(48)
            ->get();

        if ($data->last()->updated_at < now()->subHours(6)) {
            $this->updateSolcast();
        }

        return $data;
    }

    private function updateSolcast(): void
    {
        try {
            (new ActualForecastAction())->run();
        } catch (\Throwable $th) {
            Log::error('Error running actual forecast import action', ['error message' => $th->getMessage()]);
        }
    }
}
