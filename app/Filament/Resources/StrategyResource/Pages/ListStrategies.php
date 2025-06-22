<?php

namespace App\Filament\Resources\StrategyResource\Pages;

use App\Filament\Resources\StrategyResource;
use App\Filament\Resources\StrategyResource\Widgets\CostChart;
use App\Filament\Resources\StrategyResource\Widgets\ElectricImportExportChart;
use App\Filament\Resources\StrategyResource\Widgets\StrategyChart;
use App\Filament\Resources\StrategyResource\Widgets\StrategyOverview;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListStrategies extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = StrategyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getHeaderWidgets(): array
    {
        $date = now()->format('Y-m-d');
        if (isset($this->tableFilters['period']['value'])) {
            $date = $this->tableFilters['period']['value'];
        }

        $widgets = [
            StrategyOverview::class,
            CostChart::class,
            StrategyChart::class,
        ];

        if (! ($date === now()->format('Y-m-d') || $date === now()->addDay()->format('Y-m-d'))) {
            $widgets[] = ElectricImportExportChart::class;
        }

        return $widgets;
    }
}
