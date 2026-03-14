<?php

namespace App\Filament\Resources\Energy\Inverters\Pages;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GetInverterDayDataCommand;
use App\Filament\Resources\Energy\Inverters\InverterResource;
use App\Filament\Widgets\InverterCountChart;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Carbon;

class ManageInverters extends ManageRecords
{
    protected static string $resource = InverterResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            InverterCountChart::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fetchInverterData')
                ->label('Fetch Inverter Data')
                ->schema([
                    DatePicker::make('date')
                        ->default(now()->subDay())
                        ->required(),
                ])
                ->action(function (array $data, CommandBus $bus): void {
                    $date = Carbon::parse($data['date'])->format('Y-m-d');
                    $bus->dispatch(new GetInverterDayDataCommand($date));

                    Notification::make()
                        ->title('Fetching Inverter Data')
                        ->body("Data for $date has been requested.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
