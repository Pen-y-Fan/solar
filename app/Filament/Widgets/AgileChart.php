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

    protected static bool $isLazy = false;

    protected static ?string $heading = 'Agile forecast';

    protected static ?string $pollingInterval = '120s';

    /**
     * @var float The minimum value for the chart's y-axis
     */
    public float $minValue = 0.0;

    public ?string $filter = null;

    protected function getData(): array
    {
        // If the filter requested a reset, emit the event immediately and clear selection
        if ($this->filter === 'reset_zoom') {
            $this->dispatch('agile-chart:reset-zoom');
            $this->filter = null;
        }

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

        // If the filter requested a reset, pass a flag to plugins via options (handled in getOptions())
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
            ],
            // Use ISO (UTC) values for x-axis labels to ensure uniqueness across day boundaries;
            // we will format for display on the client (Europe/London) via a ticks callback.
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
        // Compute current period time (Europe/London), rounded to 00 or 30, then convert to UTC ISO for label matching
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
            // ignore, we'll fallback below
        }

        $resolvedLabel = null;
        if (!empty($labels)) {
            if (in_array($currentPeriodUtcIso, $labels, true)) {
                $resolvedLabel = $currentPeriodUtcIso;
            } else {
                // Find nearest by absolute time difference using ISO datetimes
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
        $displayAnnotation = is_string($resolvedLabel) && $resolvedLabel !== '';

        // Determine if we should request a zoom reset on init via plugin flag
        $resetOnInit = $this->filter === 'reset_zoom';

        // Clear the filter selection now that we've captured it
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
                        // Placeholder; JS plugin will replace with a real formatter for Europe/London display
                        'callback' => 'function',
                    ],
                ],
            ],
            // Configure interaction so hovering a period shows all dataset values
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            // Configure tooltip callbacks (shape only here; JS bodies are registered client-side)
            'plugins' => [
                // Minimal annotation config retained for tests (client does not use annotation plugin)
                'annotation' => [
                    'annotations' => [
                        'currentPeriod' => [
                            'type' => 'line',
                            'mode' => 'vertical',
                            'scaleID' => 'x',
                            // Keep a string value for test stability even when not displayed
                            'value' => $displayAnnotation ? (string) $resolvedLabel : '',
                            'borderColor' => 'rgba(99, 102, 241, 0.8)',
                            'borderWidth' => 1,
                            'display' => $displayAnnotation,
                        ],
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        // Title should render the period range (e.g., 13:00–13:30, full date shown as needed)
                        'title' => 'function',
                        // Label should render per-dataset currency formatted values (e.g., £0.12)
                        'label' => 'function',
                    ],
                ],
                // Lightweight current-time vertical line (custom plugin, no 3rd-party deps)
                'solarCurrentTimeLine' => [
                    // ISO label used by the custom plugin to draw the line at the exact slot
                    'label' => $resolvedLabel,
                    'color' => 'rgba(99, 102, 241, 0.8)',
                    'lineWidth' => 1,
                    'dash' => [2, 2],
                ],
                // Re-enable zoom/pan (plugin registered client-side)
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

    // Reset handled via getFilters() dropdown selection

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
