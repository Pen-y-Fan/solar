<?php

namespace App\Domain\Strategy\Actions;

use App\Domain\Strategy\Models\Strategy;
use App\Domain\Strategy\ValueObjects\ConsumptionData;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class CopyConsumptionWeekAgoAction extends Action
{
    use CanCustomizeProcess;

    private int $result = 0;

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

                foreach ($strategies as $strategy) {
                    // Ensure we're working with a Strategy model
                    if (!$strategy instanceof Strategy) {
                        continue;
                    }

                    // Get the consumption data value object
                    $consumptionData = $strategy->getConsumptionDataValueObject();

                    // Only update if there's last week data available
                    if ($consumptionData->lastWeek !== null) {
                        // Create a new consumption data object with the last week value as manual
                        $newConsumptionData = new ConsumptionData(
                            lastWeek: $consumptionData->lastWeek,
                            average: $consumptionData->average,
                            manual: $consumptionData->lastWeek
                        );

                        // Update the strategy's consumption_manual attribute
                        $strategy->consumption_manual = $newConsumptionData->manual;
                        $strategy->save();

                        $this->result++;
                    }
                }
            });

            if ($this->result > 0) {
                $this->success();
            }
        });
    }
}
