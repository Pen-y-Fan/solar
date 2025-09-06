<?php

namespace Database\Factories;

use App\Domain\Energy\Models\AgileImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgileImport>
 */
class AgileImportFactory extends Factory
{
    protected $model = AgileImport::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 day', 'now', 'UTC');
        $end = (clone $start)->modify('+30 minutes');

        return [
            'value_exc_vat' => $this->faker->randomFloat(2, 5, 50),
            'value_inc_vat' => $this->faker->randomFloat(2, 5, 60),
            'valid_from' => $start->format('Y-m-d H:i:s'),
            'valid_to' => $end->format('Y-m-d H:i:s'),
        ];
    }
}
