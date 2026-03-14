<?php

namespace App\Filament\Widgets;

use App\Domain\Energy\Models\Inverter;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InverterCountChart extends ChartWidget
{
    protected ?string $heading = 'Inverter Count Chart';

    public ?string $filter = null;

    protected function getFilters(): ?array
    {
        $filters = [];
        $now = Carbon::now();
        $this->filter = $this->filter ?: $now->format('Y-m');

        for ($i = 0; $i < 6; $i++) {
            $date = $now->copy()->subMonths($i);
            $filters[$date->format('Y-m')] = $date->format('F Y');
        }

        return $filters;
    }

    protected function getData(): array
    {
        $filter = $this->filter ?: Carbon::now()->format('Y-m');
        $date = Carbon::parse($filter);

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $data = Inverter::query()
            ->select([
                DB::raw('DATE(period) as date'),
                DB::raw('COUNT(*) as count'),
            ])
            ->whereBetween('period', [$startOfMonth, $endOfMonth])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $labels = [];
        $counts = [];

        for ($d = 1; $d <= $endOfMonth->day; $d++) {
            $currentDate = $startOfMonth->copy()->day($d)->format('Y-m-d');
            $labels[] = $d;
            $counts[] = $data[$currentDate] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Inverter Records',
                    'data' => $counts,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
