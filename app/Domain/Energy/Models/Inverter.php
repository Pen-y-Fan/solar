<?php

namespace App\Domain\Energy\Models;

use App\Domain\Energy\ValueObjects\BatteryStateOfCharge;
use App\Domain\Energy\ValueObjects\EnergyFlow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Carbon\CarbonImmutable|null $period
 * @property float|null $yield
 * @property float|null $to_grid
 * @property float|null $from_grid
 * @property float|null $consumption
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $battery_soc
 * @property \App\Domain\Energy\ValueObjects\BatteryStateOfCharge|null $battery_state_of_charge
 * @property EnergyFlow $energy_flow
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter whereBatterySoc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter whereConsumption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter whereFromGrid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter whereToGrid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inverter whereYield($value)
 * @mixin \Eloquent
 */
class Inverter extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'yield',
        'to_grid',
        'from_grid',
        'battery_soc',
        'consumption',
    ];

    protected $casts = [
        'period' => 'immutable_datetime',
    ];

    /**
     * Get the energy flow value object
     */
    public function getEnergyFlowAttribute(): EnergyFlow
    {
        return new EnergyFlow(
            yield: (float) ($this->yield ?? 0.0),
            toGrid: (float) ($this->to_grid ?? 0.0),
            fromGrid: (float) ($this->from_grid ?? 0.0),
            consumption: (float) ($this->consumption ?? 0.0)
        );
    }

    /**
     * Set the energy flow value object
     */
    public function setEnergyFlowAttribute(EnergyFlow $energyFlow): void
    {
        $data = $energyFlow->toArray();
        $this->yield = $data['yield'];
        $this->to_grid = $data['to_grid'];
        $this->from_grid = $data['from_grid'];
        $this->consumption = $data['consumption'];
    }

    /**
     * Get the battery state of charge value object
     */
    public function getBatteryStateOfChargeAttribute(): ?BatteryStateOfCharge
    {
        if ($this->battery_soc === null) {
            return null;
        }

        return new BatteryStateOfCharge(
            percentage: $this->battery_soc
        );
    }

    /**
     * Set the battery state of charge value object
     */
    public function setBatteryStateOfChargeAttribute(?BatteryStateOfCharge $batteryStateOfCharge): void
    {
        $this->battery_soc = $batteryStateOfCharge?->percentage;
    }
}
