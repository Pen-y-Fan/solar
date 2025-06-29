<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Strategy\Models\Strategy>
 */
class StrategyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \App\Domain\Strategy\Models\Strategy::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $periodOffset = 0;

        return [
            'period' => now()->startOfHour()->addHours($periodOffset++),
            'battery_charge_amount' => fake()->randomFloat(2, 0, 500),
            'import_amount' => fake()->randomFloat(2, 0, 500),
            'export_amount' => fake()->randomFloat(2, 0, 500),
            'battery_percentage_manual' => fake()->numberBetween(0, 100),
            'strategy_manual' => fake()->boolean,
            'strategy1' => fake()->boolean,
            'strategy2' => fake()->boolean,
            'consumption_last_week' => fake()->randomFloat(2, 0, 500),
            'consumption_average' => fake()->randomFloat(2, 0, 500),
            'consumption_manual' => fake()->randomFloat(2, 0, 500),
            'import_value_inc_vat' => fake()->randomFloat(2, 0.00, 99.99),
            'export_value_inc_vat' => fake()->randomFloat(2, 0.00, 99.99),
        ];
    }
}
