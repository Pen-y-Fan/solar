<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use App\Filament\Resources\StrategyResource\Pages\ListStrategies;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CostChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static ?string $heading = 'Agile forecast cost';

    protected static ?string $pollingInterval = '120s';

    public float $minValue = 0.0;

    protected function getData(): array
    {
        $data = $this->getDatabaseData();

        if ($data->count() === 0) {
            self::$heading = 'No forecast data';

            return [];
        }

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

    protected function getType(): string
    {
        return 'line';
    }

    private function getDatabaseData(): Collection
    {
        $strategies = $this->getPageTableRecords();
        $collection = new Collection();

        foreach ($strategies as $strategy) {
            $collection[] = [
                'valid_from' => $strategy->period,
                'import_value_inc_vat' => $strategy->import_value_inc_vat ?? 0,
                'export_value_inc_vat' => $strategy->export_value_inc_vat ?? 0,
            ];
        }

        $this->setMinimumValue($collection);

        return $collection;
    }

    protected function getTablePage(): string
    {
        return ListStrategies::class;
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

    private function setMinimumValue(Collection $collection): void
    {
        $min = floor(min(
            $collection->min('import_value_inc_vat'),
            $collection->min('export_value_inc_vat')
        ));

        $this->minValue = $min >= 0 ? 0 : floor($min / 5) * 5;
    }
}
