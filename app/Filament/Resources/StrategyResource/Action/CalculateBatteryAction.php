<?php

namespace App\Filament\Resources\StrategyResource\Action;

use App\Helpers\CalculateBatteryPercentage;
use App\Models\Strategy;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Actions\Concerns\CanCustomizeProcess;
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
                    [
                        $strategy->battery_percentage_manual,
                        $strategy->battery_charge_amount,
                        $strategy->import_amount,
                        $strategy->export_amount
                    ] = $batteryCalculator
                        ->isCharging($strategy->strategy_manual ?? false)
                        ->consumption($strategy->consumption_manual)
                        ->estimatePVkWh($strategy->forecast->pv_estimate)
                        ->calculate();

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
