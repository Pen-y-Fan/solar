<?php

namespace Tests\Fixtures;

use Carbon\CarbonInterface;

final class StrategyTestDto
{
    public int $id;
    public CarbonInterface $period;
    public float $consumption_average = 0.4;
    public float $consumption_last_week = 0.6;
    public float $consumption_manual = 0.5;
    public float $import_value_inc_vat = 0.50;
    public float $export_value_inc_vat = 0.15;
    public bool $strategy_manual = false;
    public bool $strategy1 = false;
    public bool $strategy2 = false;
    public object $forecast;
    public ?float $battery_percentage1 = null;
    public ?float $battery_charge_amount = null;
    public ?float $battery_percentage_manual = null;
    public ?float $import_amount = null;
    public ?float $export_amount = null;

    public function clone(): self
    {
        return clone $this;
    }
}
