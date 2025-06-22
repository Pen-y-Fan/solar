<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StrategyResource\Action\CalculateBatteryAction;
use App\Filament\Resources\StrategyResource\Action\CopyConsumptionWeekAgoAction;
use App\Filament\Resources\StrategyResource\Action\GenerateAction;
use App\Filament\Resources\StrategyResource\Pages;
use App\Filament\Resources\StrategyResource\Widgets\CostChart;
use App\Filament\Resources\StrategyResource\Widgets\ElectricImportExportChart;
use App\Filament\Resources\StrategyResource\Widgets\StrategyChart;
use App\Filament\Resources\StrategyResource\Widgets\StrategyOverview;
use App\Models\Strategy;
use Carbon\CarbonPeriod;
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
use Illuminate\Support\Carbon;

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
                    ->label('Date time')
                    ->dateTime(format: 'd M H:i', timezone: 'Europe/London')
                    ->description(fn ($record) => $record->period->clone()->setTimezone('Europe/London')->format('P')
                    !== $record->period->clone()->setTimezone('UTC')->format('P')
                        ? sprintf('(%s UTC)', $record->period->clone()->setTimezone('UTC')->format('d M H:i'))
                        : null)
                    ->sortable(),

                // TODO: validation
                Tables\Columns\TextInputColumn::make('battery_percentage_manual')
                    ->label('Battery %')
                    ->type('number'),

                Tables\Columns\TextColumn::make('battery_charge_amount')
                    ->label('Bat.charge')
                    ->description(fn ($record) => $record->battery_charge_amount * $record->import_value_inc_vat)
                    ->numeric(2)
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('import_amount')
                    ->label('Import')
                    ->description(fn ($record) => $record->import_amount * $record->import_value_inc_vat)
                    ->numeric(2)
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('export_amount')
                    ->label('Export')
                    ->description(fn ($record) => $record->export_amount * $record->export_value_inc_vat)
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
                    ->boolean(),

                // TODO: validation
                Tables\Columns\TextInputColumn::make('consumption_manual')
                    ->label('Usage')
                    ->type('number'),

                Tables\Columns\TextColumn::make('consumption_last_week')
                    ->label('Usage')
                    ->numeric(2)
                    ->toggleable()
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('consumption_average')
                    ->label('Usage avg')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('consumption_last_week_cost')
                    ->label('p')
                    ->numeric(2)
                    ->toggleable()
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('consumption_average_cost')
                    ->label('p avg')
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true)
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
                    ->label('Date')
                    ->options(self::getPeriod())
                    ->query(function (Builder $query, array $data) {

                        if (isset($data['value'])) {
                            $start = Carbon::parse($data['value'], 'Europe/London')
                                ->startOfDay()->timezone('UTC');
                            $end = Carbon::parse($data['value'], 'Europe/London')
                                ->endOfDay()->timezone('UTC');
                            $query->whereBetween('period', [$start, $end]);
                        }
                    })
                    ->default(now()->format('Y-m-d')),
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
            CostChart::class,
            ElectricImportExportChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStrategies::route('/'),
            'edit' => Pages\EditStrategy::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    protected static function getPeriod(): array
    {
        $periods = CarbonPeriod::create('now +1 day', '-1 day', 35);

        $options = [];
        foreach ($periods as $period) {
            $options[$period->format('Y-m-d')] = $period->format('D jS M');
        }

        return $options;
    }
}
