<?php

namespace App\Domain\Strategy\Actions;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\CopyConsumptionWeekAgoCommand;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;

class CopyConsumptionWeekAgoAction extends Action
{
    use CanCustomizeProcess;

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
                /** @var CommandBus $bus */
                $bus = App::make(CommandBus::class);

                // Infer selected date from the table filter if provided
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
                    // Ignore
                }

                $result = $bus->dispatch(new CopyConsumptionWeekAgoCommand(date: $date));

                if ($result->isSuccess()) {
                    $this->successNotificationTitle($result->getMessage() ?? 'Copied');
                    $this->success();
                } else {
                    $this->failureNotificationTitle($result->getMessage() ?? 'Copy failed');
                    $this->failure();
                }
            });
        });
    }
}
