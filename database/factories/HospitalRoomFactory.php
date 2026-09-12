<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HospitalRoom>
 */
class HospitalRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->numberBetween(100, 599),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
