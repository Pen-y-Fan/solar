<?php

namespace App\Filament\Resources\Energy\Inverters;

use App\Filament\Resources\Energy\Inverters\Pages\ManageInverters;
use App\Domain\Energy\Models\Inverter;
use App\Filament\Widgets\InverterCountChart;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InverterResource extends Resource
{
    protected static ?string $model = Inverter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('count')
                    ->label('Records Count')
                    ->numeric(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('period', '=', $date),
                            );
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                DB::raw('MIN(id) as id'),
                DB::raw('DATE(period) as date'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('date');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInverters::route('/'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            InverterCountChart::class,
        ];
    }
}
