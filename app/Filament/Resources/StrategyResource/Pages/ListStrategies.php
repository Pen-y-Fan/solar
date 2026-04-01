<?php

namespace App\Filament\Resources\StrategyResource\Pages;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\CalculateBatteryCommand;
use App\Domain\Strategy\Models\Strategy;
use App\Filament\Resources\StrategyResource;
use App\Filament\Resources\StrategyResource\Widgets\CostChart;
use App\Filament\Resources\StrategyResource\Widgets\ElectricImportExportChart;
use App\Filament\Resources\StrategyResource\Widgets\StrategyChart;
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

    public function mount(): void
    {
        parent::mount();

        $this->checkAndRunPendingCalculations();
    }

    public function updatedTableFilters(): void
    {
        $this->checkAndRunPendingCalculations();
    }

    private function checkAndRunPendingCalculations(): void
    {
        $date = now()->format('Y-m-d');
        if (isset($this->tableFilters['period']['value'])) {
            $date = $this->tableFilters['period']['value'];
        }

        $hasPending = Strategy::query()
            ->whereDate('period', $date)
            ->whereNull('battery_percentage1')
            ->exists();

        if ($hasPending) {
            /** @var CommandBus $bus */
            $bus = app(CommandBus::class);
            $bus->dispatch(new CalculateBatteryCommand(date: $date));
        }
    }

    public function getHeaderWidgets(): array
    {
        $date = now()->format('Y-m-d');
        if (isset($this->tableFilters['period']['value'])) {
            $date = $this->tableFilters['period']['value'];
        }

        $widgets = [
            CostChart::class,
            StrategyChart::class,
        ];

        if (! ($date === now()->format('Y-m-d') || $date === now()->addDay()->format('Y-m-d'))) {
            $widgets[] = ElectricImportExportChart::class;
        }

        return $widgets;
    }
}
