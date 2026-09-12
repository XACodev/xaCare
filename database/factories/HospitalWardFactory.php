<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HospitalWard>
 */
class HospitalWardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Sala '.$this->faker->unique()->numberBetween(1, 20),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
