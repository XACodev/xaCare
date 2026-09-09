<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\SurgeryStatus>
 */
class SurgeryStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'name' => $this->faker->unique()->word(),
            'slug' => $this->faker->unique()->slug(),
            'color' => $this->faker->hexColor(),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_default' => false,
            'is_completed' => false,
            'is_cancelled' => false,
            'active' => true,
        ];
    }
}
