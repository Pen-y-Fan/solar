<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Strategy>
 */
class StrategyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period' => now()->startOfHour(),
            'battery_percentage' => fake()->numberBetween(0, 100),
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
