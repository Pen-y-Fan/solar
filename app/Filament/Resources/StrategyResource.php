<?php

namespace App\Filament\Resources;

use App\Domain\Strategy\Actions\CalculateBatteryAction;
use App\Filament\Resources\StrategyResource\Action\GenerateAction;
use App\Filament\Resources\StrategyResource\Pages;
use App\Filament\Resources\StrategyResource\Widgets\CostChart;
use App\Filament\Resources\StrategyResource\Widgets\ElectricImportExportChart;
use App\Filament\Resources\StrategyResource\Widgets\StrategyChart;
use App\Filament\Resources\StrategyResource\Widgets\StrategyOverview;
use App\Domain\Strategy\Models\Strategy;
use Carbon\CarbonPeriod;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Range;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Contracts\HasTable;
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
                Forms\Components\Toggle::make('strategy_manual')
                    ->label('Manual Strategy')
                    ->helperText('Enable manual strategy control'),
                Forms\Components\Toggle::make('strategy1')
                    ->label('Strategy 1')
                    ->helperText('Enable strategy 1'),
                Forms\Components\Toggle::make('strategy2')
                    ->label('Strategy 2')
                    ->helperText('Enable strategy 2'),
                Forms\Components\TextInput::make('battery_percentage1')
                    ->label('Battery Percentage')
                    ->helperText('Current battery percentage (0-100)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->integer(),
                Forms\Components\TextInput::make('battery_charge_amount')
                    ->label('Battery Charge Amount')
                    ->helperText('Amount of energy to charge/discharge')
                    ->numeric(),
                Forms\Components\TextInput::make('battery_percentage_manual')
                    ->label('Manual Battery Percentage')
                    ->helperText('Manually set battery percentage (0-100)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->integer(),
                Forms\Components\TextInput::make('consumption_last_week')
                    ->label('Consumption Last Week')
                    ->helperText('Last week\'s consumption data (read-only)')
                    ->numeric()
                    ->minValue(0)
                    ->readOnly(),
                Forms\Components\TextInput::make('consumption_average')
                    ->label('Average Consumption')
                    ->helperText('Average consumption data (read-only)')
                    ->numeric()
                    ->minValue(0)
                    ->readOnly(),
                Forms\Components\TextInput::make('consumption_manual')
                    ->label('Manual Consumption')
                    ->helperText('Manually set consumption value')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('import_value_inc_vat')
                    ->label('Import Value (inc. VAT)')
                    ->helperText('Import cost including VAT')
                    ->numeric()
                    ->readOnly(),
                Forms\Components\TextInput::make('export_value_inc_vat')
                    ->label('Export Value (inc. VAT)')
                    ->helperText('Export value including VAT')
                    ->numeric()
                    ->readOnly(),
                Forms\Components\TextInput::make('consumption_average_cost')
                    ->label('Average Consumption Cost')
                    ->helperText('Cost of average consumption')
                    ->numeric()
                    ->readOnly(),
                Forms\Components\TextInput::make('consumption_last_week_cost')
                    ->label('Last Week Consumption Cost')
                    ->helperText('Cost of last week\'s consumption')
                    ->numeric()
                    ->readOnly(),
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
                    ->description(fn($record) => $record->period->clone()->setTimezone('Europe/London')->format('P')
                    !== $record->period->clone()->setTimezone('UTC')->format('P')
                        ? sprintf('(%s UTC)', $record->period->clone()->setTimezone('UTC')->format('d M H:i'))
                        : null)
                    ->sortable(),

                Tables\Columns\TextInputColumn::make('battery_percentage_manual')
                    ->label('Battery %')
                    ->type('number')
                    ->rules(['integer', 'min:0', 'max:100']),

                Tables\Columns\TextColumn::make('battery_charge_amount')
                    ->label('Bat.charge')
                    ->description(fn($record) => $record->battery_charge_amount * $record->import_value_inc_vat)
                    ->tooltip(fn($record) => $record->getBatteryStateValueObject()->isCharging()
                        ? 'Battery is charging'
                        : 'Battery is discharging')
                    ->numeric(2)
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('battery_percentage1')
                    ->label('Battery Target %')
                    ->tooltip('Target battery percentage')
                    ->numeric(0)
                    ->toggleable(),

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
                    ->boolean(),

                Tables\Columns\TextInputColumn::make('consumption_manual')
                    ->label('Man Usage')
                    ->type('number')
                    ->rules(['numeric', 'min:0']),

                Tables\Columns\TextColumn::make('consumption_last_week')
                    ->label('Usage -1wk')
                    ->tooltip('Consumption from last week')
                    ->numeric(2)
                    ->toggleable()
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('consumption_average')
                    ->label('Avg Usage')
                    ->tooltip('Average consumption')
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('best_estimate')
                    ->label('Best Estimate')
                    ->tooltip('Best consumption estimate (prioritizes manual, then last week, then average)')
                    ->getStateUsing(fn($record) => $record->getConsumptionDataValueObject()->getBestEstimate())
                    ->numeric(2)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('consumption_last_week_cost')
                    ->label('Last Week Cost')
                    ->tooltip('Cost of last week\'s consumption')
                    ->getStateUsing(fn($record) => $record->getCostDataValueObject()->consumptionLastWeekCost)
                    ->numeric(2)
                    ->toggleable()
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('consumption_average_cost')
                    ->label('Avg Cost')
                    ->tooltip('Cost of average consumption')
                    ->getStateUsing(fn($record) => $record->getCostDataValueObject()->consumptionAverageCost)
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize([Sum::make(), Range::make()]),

                Tables\Columns\TextColumn::make('best_consumption_cost')
                    ->label('Best Cost Est.')
                    ->tooltip('Best consumption cost estimate (prioritizes last week, then average)')
                    ->getStateUsing(fn($record) => $record->getCostDataValueObject()->getBestConsumptionCostEstimate())
                    ->numeric(2)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('net_cost')
                    ->label('Net Cost')
                    ->tooltip('Net cost (import cost minus export value)')
                    ->getStateUsing(fn($record) => $record->getCostDataValueObject()->getNetCost())
                    ->numeric(2)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('forecast.pv_estimate')
                    ->label('PV')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable()
                    ->summarize(Sum::make()),

                Tables\Columns\TextColumn::make('import_value_inc_vat')
                    ->label('Import')
                    ->getStateUsing(fn($record) => $record->getCostDataValueObject()->importValueIncVat)
                    ->numeric(2)
                    ->sortable()
                    ->toggleable()
                    ->summarize([Average::make(), Range::make()]),

                Tables\Columns\TextColumn::make('export_value_inc_vat')
                    ->label('Export')
                    ->getStateUsing(fn($record) => $record->getCostDataValueObject()->exportValueIncVat)
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
                            try {
                                $day = Carbon::parse($data['value'], 'Europe/London');
                            } catch (InvalidFormatException $e) {
                                $day = now('Europe/London');
                            }
                            $start = $day->copy()->subDay()->setTime(16, 0)->timezone('UTC');
                            $end = $start->copy()->addDay();
                            $query->whereBetween('period', [$start, $end]);
                        }
                    })
                    ->default(
                        fn() => now('Europe/London') < now('Europe/London')->setTime(16, 0)
                            ? now('Europe/London')->format('Y-m-d')
                            : now('Europe/London')->addDay()->format('Y-m-d')
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->headerActions([
                GenerateAction::make()
                    ->visible(fn (HasTable $livewire) => $livewire->getAllTableRecordsCount() < 20),
                CalculateBatteryAction::make()
                    ->visible(fn (HasTable $livewire) => $livewire->getAllTableRecordsCount() >= 20),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
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
            'edit'  => Pages\EditStrategy::route('/{record}/edit'),
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
            $options[$period->format('Y-m-d')] = sprintf(
                '%s - %s',
                $period->copy()->timezone('Europe/London')->subDay()->setTime(16, 0)->format('D jS M H:i'),
                $period->copy()->timezone('Europe/London')->setTime(16, 0)->format('D jS M H:i')
            );
        }

        return $options;
    }
}
