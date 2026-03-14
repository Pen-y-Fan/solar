<?php

namespace Database\Factories;

use App\Domain\Energy\Models\Inverter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inverter>
 */
class InverterFactory extends Factory
{
    /**
     * @var class-string<Inverter>
     */
    protected $model = Inverter::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'period' => fake()->dateTimeBetween('-1 year'),
            'yield' => fake()->randomFloat(2, 0, 10),
            'to_grid' => fake()->randomFloat(2, 0, 5),
            'from_grid' => fake()->randomFloat(2, 0, 5),
            'battery_soc' => fake()->numberBetween(0, 100),
            'consumption' => fake()->randomFloat(2, 0, 5),
        ];
    }
}
