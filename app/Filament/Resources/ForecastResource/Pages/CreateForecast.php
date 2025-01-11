<?php

namespace App\Filament\Resources\ForecastResource\Pages;

use App\Filament\Resources\ForecastResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateForecast extends CreateRecord
{
    protected static string $resource = ForecastResource::class;
}
