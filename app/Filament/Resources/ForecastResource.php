<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForecastResource\Pages;
use App\Filament\Resources\ForecastResource\Widgets\ForecastChartWidget;
use App\Filament\Resources\ForecastResource\Widgets\SolcastAllowanceStatusWidget;
use App\Filament\Resources\ForecastResource\Widgets\SolcastAllowanceLogsWidget;
use App\Domain\Forecasting\Models\Forecast;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ForecastResource extends Resource
{
    protected static ?string $model = Forecast::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('period_end'),
                Forms\Components\TextInput::make('pv_estimate')
                    ->label('PV Estimate')
                    ->numeric()
                    ->afterStateHydrated(function ($record, $state, $component) {
                        // If we have a record, use the value object
                        if ($record) {
                            $pvEstimate = $record->getPvEstimateValueObject();
                            $component->state($pvEstimate->estimate);
                        }
                    })
                    ->dehydrateStateUsing(function ($state, $record) {
                        // When saving, update the value object
                        if ($record) {
                            $pvEstimate = $record->getPvEstimateValueObject();
                            $newPvEstimate = new \App\Domain\Forecasting\ValueObjects\PvEstimate(
                                estimate: $state,
                                estimate10: $pvEstimate->estimate10,
                                estimate90: $pvEstimate->estimate90
                            );
                            $record->setPvEstimateValueObject($newPvEstimate);
                        }
                        return $state;
                    }),
                Forms\Components\TextInput::make('pv_estimate10')
                    ->label('PV Estimate (10th percentile)')
                    ->numeric()
                    ->afterStateHydrated(function ($record, $state, $component) {
                        if ($record) {
                            $pvEstimate = $record->getPvEstimateValueObject();
                            $component->state($pvEstimate->estimate10);
                        }
                    })
                    ->dehydrateStateUsing(function ($state, $record) {
                        if ($record) {
                            $pvEstimate = $record->getPvEstimateValueObject();
                            $newPvEstimate = new \App\Domain\Forecasting\ValueObjects\PvEstimate(
                                estimate: $pvEstimate->estimate,
                                estimate10: $state,
                                estimate90: $pvEstimate->estimate90
                            );
                            $record->setPvEstimateValueObject($newPvEstimate);
                        }
                        return $state;
                    }),
                Forms\Components\TextInput::make('pv_estimate90')
                    ->label('PV Estimate (90th percentile)')
                    ->numeric()
                    ->afterStateHydrated(function ($record, $state, $component) {
                        if ($record) {
                            $pvEstimate = $record->getPvEstimateValueObject();
                            $component->state($pvEstimate->estimate90);
                        }
                    })
                    ->dehydrateStateUsing(function ($state, $record) {
                        if ($record) {
                            $pvEstimate = $record->getPvEstimateValueObject();
                            $newPvEstimate = new \App\Domain\Forecasting\ValueObjects\PvEstimate(
                                estimate: $pvEstimate->estimate,
                                estimate10: $pvEstimate->estimate10,
                                estimate90: $state
                            );
                            $record->setPvEstimateValueObject($newPvEstimate);
                        }
                        return $state;
                    }),
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
                    ->label('PV Estimate')
                    ->numeric()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->getPvEstimateValueObject()->estimate),
                Tables\Columns\TextColumn::make('pv_estimate10')
                    ->label('PV Estimate (10th percentile)')
                    ->numeric()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->getPvEstimateValueObject()->estimate10),
                Tables\Columns\TextColumn::make('pv_estimate90')
                    ->label('PV Estimate (90th percentile)')
                    ->numeric()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->getPvEstimateValueObject()->estimate90),
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
            SolcastAllowanceStatusWidget::class,
            SolcastAllowanceLogsWidget::class,
        ];
    }
}
