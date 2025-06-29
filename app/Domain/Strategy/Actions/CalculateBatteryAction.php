<?php

namespace App\Domain\Strategy\Actions;

use App\Helpers\CalculateBatteryPercentage;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\Strategy\ValueObjects\BatteryState;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class CalculateBatteryAction extends Action
{
    use CanCustomizeProcess;

    private bool $result = false;

    public static function getDefaultName(): ?string
    {
        return 'Calculate battery';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Calculate battery');

        $this->color('success');

        $this->modalSubmitActionLabel('Calculate');

        $this->successNotificationTitle('Calculated');

        $this->icon('heroicon-m-calculator');

        $this->action(function (): void {
            $this->process(function (Table $table) {

                $strategies = $table->getQuery()->get();

                $batteryCalculator = new CalculateBatteryPercentage();

                $currentBattery = 10;

                $batteryCalculator->startBatteryPercentage($currentBattery);

                $strategies->each(function (Strategy $strategy) use ($batteryCalculator) {
                    Log::debug('strategy', $strategy->toArray());

                    // Get consumption data from the value object
                    $consumptionData = $strategy->getConsumptionDataValueObject();
                    $manualConsumption = $consumptionData->manual;

                    // Calculate battery values
                    [
                        $batteryPercentage,
                        $chargeAmount,
                        $importAmount,
                        $exportAmount
                    ] = $batteryCalculator
                        ->isCharging($strategy->strategy_manual ?? false)
                        ->consumption($manualConsumption ?? 0.0)
                        ->estimatePVkWh($strategy->forecast->pv_estimate)
                        ->calculate();

                    // Create a BatteryState value object with the calculated values
                    $batteryState = new BatteryState(
                        percentage: $batteryPercentage,
                        chargeAmount: $chargeAmount,
                        manualPercentage: $batteryPercentage // Using calculated percentage as manual
                    );

                    // Update the strategy using the value object's properties
                    $strategy->battery_percentage1 = $batteryState->percentage;
                    $strategy->battery_charge_amount = $batteryState->chargeAmount;
                    $strategy->battery_percentage_manual = $batteryState->manualPercentage;

                    // Set other calculated values
                    $strategy->import_amount = $importAmount;
                    $strategy->export_amount = $exportAmount;

                    $strategy->save();
                });
                $this->result = true;
            });

            if ($this->result) {
                $this->success();
            }
        });
    }
}
