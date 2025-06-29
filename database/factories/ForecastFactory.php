<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Forecasting\Models\Forecast>
 */
class ForecastFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \App\Domain\Forecasting\Models\Forecast::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $periodOffset = 0;

        return [
            'period_end' => now()->startOfHour()->addHours($periodOffset++),
            'pv_estimate' => fake()->randomFloat(2, 0, 500),
            'pv_estimate10' => fake()->randomFloat(2, 0, 500),
            'pv_estimate90' => fake()->randomFloat(2, 0, 500),
        ];
    }
}
