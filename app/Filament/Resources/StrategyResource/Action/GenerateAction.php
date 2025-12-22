<?php

namespace App\Filament\Resources\StrategyResource\Action;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GenerateStrategyCommand;
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

        $this->failureNotificationTitle('Failed');

        $this->icon('heroicon-m-plus-circle');

        $this->action(function (): void {
            $this->process(function (Table $table) {
                $periodFilter = $table->getFilter('period')->getState();
                $periodValue = $periodFilter['value'] ?? null;
                Log::info('Generating strategy for: ' . $periodValue);
                /** @var CommandBus $bus */
                $bus = app(CommandBus::class);
                $result = $bus->dispatch(new GenerateStrategyCommand(period: $periodValue));
                $this->result = $result->isSuccess();
                if (!$this->result) {
                    $this->failureNotificationTitle($result->getMessage() ?? 'Failed');
                    $this->failure();
                }
            });

            if ($this->result) {
                $this->success();
            }
        });
    }
}
