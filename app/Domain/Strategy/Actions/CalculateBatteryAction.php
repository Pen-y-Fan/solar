<?php

namespace App\Domain\Strategy\Actions;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\CalculateBatteryCommand;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;

class CalculateBatteryAction extends Action
{
    use CanCustomizeProcess;

    public static function getDefaultName(): ?string
    {
        return 'Calculate battery';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Recalculate battery');

        $this->color('info');

        $this->modalSubmitActionLabel('Calculate');

        $this->successNotificationTitle('Calculated');

        $this->icon('heroicon-m-calculator');

        $this->action(function (): void {
            $this->process(function (Table $table) {
                // Dispatch the CQRS command instead of calculating here
                /** @var CommandBus $bus */
                $bus = App::make(CommandBus::class);

                // Try to infer the selected date from the table filter if provided
                $date = null;
                try {
                    $filtersForm = $table->getFiltersForm();
                    /** @var array<string,mixed> $state */
                    $state = $filtersForm->getState();
                    /** @var string|null $selected */
                    $selected = $state['period']['value'] ?? null;
                    if (is_string($selected) && $selected !== '') {
                        $date = $selected;
                    }
                } catch (\Throwable) {
                    // Ignore and let the command default to 'today'
                }

                $result = $bus->dispatch(new CalculateBatteryCommand(date: $date));

                if ($result->isSuccess()) {
                    $this->successNotificationTitle($result->getMessage() ?? 'Calculated');
                    $this->success();
                } else {
                    $this->failureNotificationTitle($result->getMessage() ?? 'Battery calculation failed');
                    $this->failure();
                }
            });
        });
    }
}
