<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdmissionType>
 */
class AdmissionTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Tipo '.$this->faker->unique()->numberBetween(1, 20),
            'slug' => $this->faker->unique()->slug(),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
