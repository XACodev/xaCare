<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\OperatingRoom>
 */
class OperatingRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Quirófano '.$this->faker->unique()->numberBetween(1, 20),
            'is_default' => false,
            'default_for_procedure_types' => null,
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
