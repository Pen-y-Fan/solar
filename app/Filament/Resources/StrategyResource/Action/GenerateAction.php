<?php

namespace App\Filament\Resources\StrategyResource\Action;

use App\Actions\GenerateStrategyAction;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class GenerateAction extends Action
{
    use CanCustomizeProcess;

    private bool $result;

    public static function getDefaultName(): ?string
    {
        return 'Generate strategy';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Generate Strategy');

        $this->color('success');

        $this->modalSubmitActionLabel('Generate');

        $this->successNotificationTitle('Generated');

        $this->icon('heroicon-m-plus-circle');

        $this->action(function (): void {
            $this->process(function (Table $table) {
                $periodFilter = $table->getFilter('period')->getState();
                $periodValue = $periodFilter['value'] ?? null;

                if ($periodValue === null) {
                    return;
                }

                Log::debug('Generating strategy for: ' . $periodValue);
                $action = new GenerateStrategyAction();
                $action->filter = $periodValue;
                $this->result = $action->run();
            });

            if ($this->result) {
                $this->success();
            }
        });
    }
}
