<?php

namespace App\Filament\Resources\StrategyResource\Action;

use App\Models\Strategy;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class CopyConsumptionWeekAgoAction extends Action
{
    use CanCustomizeProcess;

    private bool $result;

    public static function getDefaultName(): ?string
    {
        return 'Copy week ago';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Copy consumption from week ago');

        $this->color('success');

        $this->modalSubmitActionLabel('Copy');

        $this->successNotificationTitle('Copied');

        $this->icon('heroicon-m-document-duplicate');

        $this->action(function (): void {
            $this->process(function (Table $table) {

                $strategies = $table->getQuery()->get();

                $updatedManualConsumptionIds = $strategies->map(function (Strategy $strategy) {
                    return $strategy->id;
                })->toArray();

                $this->result = Strategy::whereIn('id', $updatedManualConsumptionIds) // Apply filter(s)
                    ->update([
                        'consumption_manual' => DB::raw('consumption_last_week'),
                    ]);
            });

            if ($this->result) {
                $this->success();
            }
        });
    }
}
