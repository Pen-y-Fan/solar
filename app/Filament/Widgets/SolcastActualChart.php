<?php

namespace App\Filament\Widgets;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Forecasting\RequestSolcastActual;
use App\Domain\Forecasting\Models\ActualForecast;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SolcastActualChart extends ChartWidget
{
    protected static ?string $heading = 'Solcast actual';

    protected static ?string $pollingInterval = '120s';

    private const UPDATE_FREQUENCY_HOURS = 6;

    protected function getData(): array
    {
        $rawData = $this->getDatabaseData();

        if ($rawData->count() === 0) {
            self::$heading = 'No data for Solis actual';

            return [];
        }
        $lastRecord = $rawData->last();

        self::$heading = sprintf(
            'Solcast actual from %s to %s (last updated %s)',
            Carbon::parse($rawData->first()['period_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            Carbon::parse($lastRecord['period_end'], 'UTC')
                ->timezone('Europe/London')
                ->format('jS M H:i'),
            Carbon::parse($lastRecord['updated_at'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M H:i')
        );

        return [
            'datasets' => [
                [
                    'label' => 'Solcast actual',
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
        $lastUpdate = ActualForecast::query()
            ->latest('period_end')
            ->first();

        if (is_null($lastUpdate) || $lastUpdate->updated_at < now()->subHours(self::UPDATE_FREQUENCY_HOURS)) {
            $this->updateSolcast();
        }

        return ActualForecast::query()
            ->where('period_end', '>=', now()->startOfHour()->subDay())->orderBy('period_end')
            ->limit(48)
            ->get();
    }

    private function updateSolcast(): void
    {
        try {
            /** @var CommandBus $bus */
            $bus = app(CommandBus::class);
            $bus->dispatch(new RequestSolcastActual());
        } catch (Throwable $th) {
            Log::error('Error running actual forecast import action', ['error message' => $th->getMessage()]);
        }
    }
}
