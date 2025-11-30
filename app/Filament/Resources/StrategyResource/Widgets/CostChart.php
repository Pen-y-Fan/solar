<?php

namespace App\Filament\Resources\StrategyResource\Widgets;

use App\Domain\Strategy\Models\Strategy;
use App\Application\Queries\Energy\EnergyCostBreakdownByDayQuery;
use App\Filament\Resources\StrategyResource\Pages\ListStrategies;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CostChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '400px';

    protected static bool $isLazy = false;

    protected static ?string $heading = 'Agile forecast cost';

    protected static ?string $pollingInterval = '120s';

    public float $minValue = 0.0;

    public ?string $filter = null;

    protected function getData(): array
    {
        // Handle Reset Zoom via filter dropdown
        if ($this->filter === 'reset_zoom') {
            $this->dispatch('agile-chart:reset-zoom');
            $this->filter = null;
        }

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
        $averageNetCost = $data->sum('net_cost') / $data->count();

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
                    'label' => 'Net cost (Import - Export)',
                    'data' => $data->map(fn ($item): string => $item['net_cost']),
                    'fill' => false,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'stepped' => 'middle',
                ],
                [
                    'label' => 'Average export value',
                    'data' => $data->map(fn ($item): string => number_format($averageExport, 2)),
                    'type' => 'line',
                    'borderDash' => [5, 10],
                    'pointRadius' => 0,
                    'borderColor' => 'rgb(75, 192, 192)',
                ],
                [
                    'label' => 'Average import value',
                    'data' => $data->map(fn ($item): string => number_format($averageImport, 2)),
                    'borderDash' => [5, 10],
                    'type' => 'line',
                    'pointRadius' => 0,
                    'borderColor' => 'rgb(255, 99, 132)',
                ],
                [
                    'label' => 'Average net cost',
                    'data' => $data->map(fn ($item): string => number_format($averageNetCost, 2)),
                    'borderDash' => [5, 10],
                    'type' => 'line',
                    'pointRadius' => 0,
                    'borderColor' => 'rgb(54, 162, 235)',
                ],
            ],
            // Use ISO (UTC) for x-axis labels to ensure uniqueness across day boundaries.
            // A JS ticks formatter will render human-friendly labels in Europe/London.
            'labels' => $data->map(fn ($item): string => Carbon::parse($item['valid_from'], 'UTC')->toIso8601String()),
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            '' => 'Choose option',
            'reset_zoom' => 'Reset zoom',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function getDatabaseData(): Collection
    {
        /** @var Paginator|\Illuminate\Database\Eloquent\Collection<int, Strategy> $strategies */
        $strategies = $this->getPageTableRecords();

        // Ensure we pass a Collection to the query, even if the page table returns a paginator
        if ($strategies instanceof Paginator) {
            /** @var Collection<int, Strategy> $strategyCollection */
            $strategyCollection = collect($strategies->items());
        } else {
            /** @var Collection<int, Strategy> $strategyCollection */
            $strategyCollection = $strategies;
        }

        $query = app(EnergyCostBreakdownByDayQuery::class);
        $collection = $query->run($strategyCollection);

        $this->setMinimumValueFrom($collection);

        return $collection;
    }

    protected function getTablePage(): string
    {
        return ListStrategies::class;
    }

    protected function getOptions(): array
    {
        // Compute current period time (Europe/London), snap to :00/:30, then convert to UTC ISO for label matching
        $nowLondon = now()->timezone('Europe/London')->second(0);
        $minute = (int) $nowLondon->minute;
        if ($minute < 30) {
            $nowLondon->minute(0);
        } else {
            $nowLondon->minute(30);
        }
        $currentPeriodUtcIso = $nowLondon->clone()->timezone('UTC')->toIso8601String();

        // Resolve an annotation label that exists in the x-axis labels: prefer exact match, else nearest slot.
        $labels = [];
        try {
            $data = $this->getData();
            if (isset($data['labels']) && is_iterable($data['labels'])) {
                $labels = is_array($data['labels']) ? $data['labels'] : iterator_to_array($data['labels']);
            }
        } catch (\Throwable) {
            // ignore; fall back below
        }

        $resolvedLabel = null;
        if (!empty($labels)) {
            if (in_array($currentPeriodUtcIso, $labels, true)) {
                $resolvedLabel = $currentPeriodUtcIso;
            } else {
                $targetTs = $nowLondon->clone()->timezone('UTC')->getTimestamp();
                $bestDiff = PHP_INT_MAX;
                foreach ($labels as $lbl) {
                    try {
                        $ts = Carbon::parse((string) $lbl, 'UTC')->getTimestamp();
                    } catch (\Throwable) {
                        $ts = PHP_INT_MIN;
                    }
                    $diff = abs($ts - $targetTs);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $resolvedLabel = (string) $lbl;
                    }
                }
            }
        }

        // Determine if we should request a zoom reset on init via plugin flag
        $resetOnInit = $this->filter === 'reset_zoom';

        if ($resetOnInit) {
            $this->filter = null;
        }

        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'min' => $this->minValue,
                ],
                'x' => [
                    'ticks' => [
                        // Placeholder: client-side plugin formats ISO to human labels (Europe/London)
                        'callback' => 'function',
                    ],
                ],
            ],
            // Hover should aggregate across datasets for the time slot
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                // Minimal annotation config retained for tests
                'annotation' => [
                    'annotations' => [
                        'currentPeriod' => [
                            'type' => 'line',
                            'mode' => 'vertical',
                            'scaleID' => 'x',
                            'value' => is_string($resolvedLabel) ? $resolvedLabel : '',
                            'borderColor' => 'rgba(99, 102, 241, 0.8)',
                            'borderWidth' => 1,
                            'display' => is_string($resolvedLabel) && $resolvedLabel !== '',
                        ],
                    ],
                ],
                // Custom helper namespace to allow reset on init from PHP options
                'solarReset' => [
                    'resetOnInit' => $resetOnInit,
                ],
                'tooltip' => [
                    'callbacks' => [
                        // The JS plugin registered in resources/js/filament-chart-js-plugins.js fills these in
                        'title' => 'function',
                        'label' => 'function',
                    ],
                ],
                // Lightweight current-time vertical line (custom plugin)
                'solarCurrentTimeLine' => [
                    'label' => $resolvedLabel,
                    'color' => 'rgba(99, 102, 241, 0.8)',
                    'lineWidth' => 1,
                    'dash' => [2, 2],
                ],
                // zoom/pan (plugin registered client-side)
                'zoom' => [
                    'zoom' => [
                        'wheel' => [
                            'enabled' => true,
                            'speed' => 0.05,
                        ],
                        'pinch' => ['enabled' => true],
                        'mode' => 'x',
                    ],
                    'pan' => [
                        'enabled' => true,
                        'threshold' => 15,
                        'mode' => 'x',
                    ],
                ],
            ],
        ];
    }

    private function setMinimumValueFrom(Collection $collection): void
    {
        $min = floor(min(
            $collection->min('import_value_inc_vat'),
            $collection->min('export_value_inc_vat'),
            $collection->min('net_cost')
        ));

        $this->minValue = $min >= 0 ? 0 : floor($min / 5) * 5;
    }
}
