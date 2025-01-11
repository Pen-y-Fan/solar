<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForecastResource\Pages;
use App\Filament\Resources\ForecastResource\Widgets\ForecastChartWidget;
use App\Models\Forecast;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ForecastResource extends Resource
{
    protected static ?string $model = Forecast::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('period_end'),
                Forms\Components\TextInput::make('pv_estimate')
                    ->numeric(),
                Forms\Components\TextInput::make('pv_estimate10')
                    ->numeric(),
                Forms\Components\TextInput::make('pv_estimate90')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_end')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pv_estimate')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pv_estimate10')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pv_estimate90')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForecasts::route('/'),
            'create' => Pages\CreateForecast::route('/create'),
            'edit' => Pages\EditForecast::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            ForecastChartWidget::class,
        ];
    }
}
