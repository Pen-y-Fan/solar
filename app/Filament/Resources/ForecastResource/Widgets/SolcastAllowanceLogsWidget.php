<?php

declare(strict_types=1);

namespace App\Filament\Resources\ForecastResource\Widgets;

use App\Domain\Forecasting\Models\SolcastAllowanceLog;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;

class SolcastAllowanceLogsWidget extends BaseWidget
{
    protected static ?string $heading = 'Solcast Allowance Logs';

    protected int|string|array $columnSpan = 'full';

    public function isVisible(): bool
    {
        return (bool) config('solcast.allowance.log_to_db', false);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                SolcastAllowanceLog::query()->latest('created_at')->limit(25)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('At')->dateTime('Y-m-d H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('event_type')->label('Event')->badge(),
                Tables\Columns\TextColumn::make('endpoint')->label('Endpoint')->toggleable(),
                Tables\Columns\TextColumn::make('reason')->label('Reason')->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('HTTP')->toggleable(),
                Tables\Columns\TextColumn::make('backoff_until')->label('Backoff Until')->dateTime()->toggleable(),
            ])
            ->paginationPageOptions([25])
            ->paginated(false);
    }
}
