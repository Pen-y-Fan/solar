<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StrategyResource\Action\CalculateBatteryAction;
use App\Filament\Resources\StrategyResource\Action\CopyConsumptionWeekAgoAction;
use App\Filament\Resources\StrategyResource\Action\GenerateAction;
use App\Filament\Resources\StrategyResource\Pages;
use App\Filament\Resources\StrategyResource\Widgets\StrategyChart;
use App\Filament\Resources\StrategyResource\Widgets\StrategyOverview;
use App\Models\Strategy;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Range;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StrategyResource extends Resource
{
    protected static ?string $model = Strategy::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('period')->readOnly(),
                Forms\Components\Toggle::make('strategy_manual'),
                Forms\Components\Toggle::make('strategy1'),
                Forms\Components\Toggle::make('strategy2'),
                Forms\Components\TextInput::make('consumption_last_week')
                    ->numeric()->readOnly(),
                Forms\Components\TextInput::make('consumption_average')
                    ->numeric()->readOnly(),
                Forms\Components\TextInput::make('consumption_manual')
                    ->numeric(),
                Forms\Components\TextInput::make('import_value_inc_vat')
                    ->numeric()->readOnly(),
                Forms\Components\TextInput::make('export_value_inc_vat')
                    ->numeric()->readOnly(),
            ]);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->dateTime(format: 'd/m/y H:i', timezone: 'Europe/London')
                    ->sortable(),

                // TODO: validation
                Tables\Columns\TextInputColumn::make('battery_percentage_manual')
                    ->label('Battery %')
                    ->type('number'),

                Tables\Columns\TextColumn::make('battery_charge_amount')
                    ->label('Bat.charge')
                    ->description(fn($record) => $record->battery_charge_amount * $record->import_value_inc_vat)
                    ->numeric(2)
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('import_amount')
                    ->label('Import')
                    ->description(fn($record) => $record->import_amount * $record->import_value_inc_vat)
                    ->numeric(2)
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('export_amount')
                    ->label('Export')
                    ->description(fn($record) => $record->export_amount * $record->export_value_inc_vat)
                    ->numeric(2)
                    ->summarize(Sum::make()),

                Tables\Columns\ToggleColumn::make('strategy_manual')
                    ->label('strat'),

                Tables\Columns\IconColumn::make('strategy1')
                    ->label('strat1')
                    ->toggleable()
                    ->boolean(),

                Tables\Columns\IconColumn::make('strategy2')
                    ->label('stat2')
                    ->toggleable()
                    ->boolean(2),

                // TODO: validation
                Tables\Columns\TextInputColumn::make('consumption_manual')
                    ->label('Usage')
                    ->type('number'),

                Tables\Columns\TextColumn::make('consumption_last_week')
                    ->label('Usage -1wk')
                    ->numeric(2)
                    ->toggleable()
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('consumption_average')
                    ->label('Usage avg')
                    ->numeric()
                    ->toggleable()
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('consumption_last_week_cost')
                    ->label('p -1wk')
                    ->numeric(2)
                    ->toggleable()
                    ->summarize([Sum::make(), Range::make()]),

                Tables\Columns\TextColumn::make('consumption_average_cost')
                    ->label('p avg')
                    ->numeric(2)
                    ->toggleable()
                    ->summarize([Sum::make(), Range::make()]),

                Tables\Columns\TextColumn::make('forecast.pv_estimate')
                    ->label('PV')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable()
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('import_value_inc_vat')
                    ->label('Import')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable()
                    ->summarize([Average::make(), Range::make()]),

                Tables\Columns\TextColumn::make('export_value_inc_vat')
                    ->label('Export')
                    ->numeric(2)
                    ->toggleable()
                    ->summarize([Average::make(), Range::make()]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->dateTime(format: 'd/m/y H:i', timezone: 'Europe/London')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->dateTime(format: 'd/m/y H:i', timezone: 'Europe/London')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginationPageOptions([48])
            ->defaultPaginationPageOption(48)
            ->defaultSort('period')
            ->filters([

                Tables\Filters\SelectFilter::make('period')
                    ->label('Date Range')
                    ->options([
                        'today' => 'Today',
                        'tomorrow' => 'Tomorrow',
                    ])
                    ->query(function (Builder $query, array $data) {

                        if ($data['value'] === 'today') {
                            $startOfDay = now()->setTimezone('GMT')->startOfDay();
                            $endOfDay = now()->setTimezone('GMT')->endOfDay();
                            $query->whereBetween('period', [$startOfDay, $endOfDay]);
                        } elseif ($data['value'] === 'tomorrow') {
                            $startOfTomorrow = now()->setTimezone('GMT')->addDay()->startOfDay();
                            $endOfTomorrow = now()->setTimezone('GMT')->addDay()->endOfDay();
                            $query->whereBetween('period', [$startOfTomorrow, $endOfTomorrow]);
                        }
                    })
                    ->default('today'),
                //
            ], layout: FiltersLayout::AboveContent)
            ->headerActions([
                GenerateAction::make(),
                CopyConsumptionWeekAgoAction::make(),
                CalculateBatteryAction::make(),
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

    public static function getWidgets(): array
    {
        return [
            StrategyOverview::class,
            StrategyChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStrategies::route('/'),
            'create' => Pages\CreateStrategy::route('/create'),
            'edit' => Pages\EditStrategy::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
