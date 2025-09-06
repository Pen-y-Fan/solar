<?php

namespace App\Filament\Widgets;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Energy\ExportAgileRatesCommand;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Application\Queries\Energy\AgileImportExportSeriesQuery;
use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AgileChart extends ChartWidget
{
    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Agile forecast';

    protected static ?string $pollingInterval = '120s';

    /**
     * @var float The minimum value for the chart's y-axis
     */
    public float $minValue = 0.0;

    protected function getData(): array
    {
        $data = $this->getDatabaseData();

        self::$heading = sprintf(
            'Agile costs from %s to %s',
            Carbon::parse($data->first()['valid_from'], 'UTC')
                ->timezone('Europe/London')
                ->format('D jS M Y H:i'),
            Carbon::parse($data->last()['valid_from'], 'UTC')
                ->timezone('Europe/London')
                ->format('jS M H:i'),
        );

        $averageExport = $data->sum('export_value_inc_vat') / $data->count();
        $averageImport = $data->sum('import_value_inc_vat') / $data->count();

        return [
            'datasets' => [
                [
                    'label' => 'Export value',
                    'data' => $data->map(fn ($item): string => $item['export_value_inc_vat']),
                    'fill' => true,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
                    'stepped' => 'middle',
                ],
                [
                    'label' => 'Import value',
                    'data' => $data->map(fn ($item): string => $item['import_value_inc_vat']),
                    'fill' => '-1',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'stepped' => 'middle',
                ],
                [
                    'label' => sprintf('Average export value (%0.02f)', $averageExport),
                    'data' => $data->map(fn ($item): string => number_format($averageExport, 2)),
                    'type' => 'line',
                    'borderDash' => [5, 10],
                    'pointRadius' => 0,
                    'borderColor' => 'rgb(75, 192, 192)',
                ],

                [
                    'label' => sprintf('Average import value (%0.02f)', $averageImport),
                    'data' => $data->map(fn ($item): string => number_format($averageImport, 2)),
                    'borderDash' => [5, 10],
                    'type' => 'line',
                    'pointRadius' => 0,
                    'borderColor' => 'rgb(255, 99, 132)',
                ],
            ],
            'labels' => $data->map(function ($item): string {
                $date = Carbon::parse($item['valid_from'], 'UTC')
                    ->timezone('Europe/London');

                $format = $date->format('H:i') === '00:00' ? 'j M H:i' : 'H:i';

                return $date->format($format);
            }),
        ];
    }

    private function getDatabaseData(): Collection
    {
        $limit = 96;
        $start = now()->timezone('Europe/London')->startOfDay()->timezone('UTC');

        // TODO: convert to a command
        $lastImport = AgileImport::query()
            ->where(
                'valid_from',
                '>=',
                $start
            )
            ->orderBy('valid_from', 'DESC')
            ->first();

        if (is_null($lastImport)) {
            // No import data, so update
            $this->updateAgileImport();
        } elseif (now()->diffInUTCHours($lastImport->valid_from) < 7) {
            // Don't download if we have more than 7 hours of data from now, data is normally available after 4 PM.
            // We should have data up to 11 PM, 4 PM is 7 hours before 11pm.
            $this->updateAgileImport();
        }

        // TODO: convert to a command
        $lastExport = AgileExport::query()
            ->where(
                'valid_from',
                '>=',
                $start
            )
            ->orderBy('valid_from', 'DESC')
            ->first();

        if (is_null($lastExport)) {
            // No export data, so update
            $this->updateAgileExport();
        } elseif (now()->diffInUTCHours($lastExport->valid_from) < 7) {
            // Don't download if we have more than 7 hours of data from now, data is normally available after 4 PM.
            // We should have data up to 11 PM, 4 PM is 7 hours before 11pm.
            $this->updateAgileExport();
        }

        $series = app(AgileImportExportSeriesQuery::class)->run($start, $limit);

        $min = floor(collect($series)->min('import_value_inc_vat') ?? 1) - 1;

        if ($min < 0) {
            $this->minValue = floor($min / 5) * 5;
        } else {
            // If there are no negative values, use 0 as the minimum
            $this->minValue = 0;
        }

        return $series;
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
                    'min' => $this->minValue,
                ],
            ],
        ];
    }

    private function updateAgileImport(): void
    {
        Log::info('Updating agile import chart data from API.');

        try {
            /** @var CommandBus $bus */
            $bus = app(CommandBus::class);
            $bus->dispatch(new ImportAgileRatesCommand());
        } catch (Throwable $th) {
            Log::error('Error dispatching ImportAgileRatesCommand:', ['error message' => $th->getMessage()]);
        }
    }

    private function updateAgileExport(): void
    {
        Log::info('Updating agile export chart data from API.');

        try {
            /** @var CommandBus $bus */
            $bus = app(CommandBus::class);
            $bus->dispatch(new ExportAgileRatesCommand());
        } catch (Throwable $th) {
            Log::error('Error dispatching ExportAgileRatesCommand:', ['error message' => $th->getMessage()]);
        }
    }
}
