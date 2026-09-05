<?php

namespace Database\Factories;

use App\Modules\Insurance\Models\InsurancePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Insurance\Models\Coverage>
 */
class CoverageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'insurance_policy_id' => InsurancePolicy::factory(),
            'type' => $this->faker->randomElement(['hospitalizacion', 'consulta', 'medicamentos', 'cirugia']),
            'percentage' => $this->faker->randomFloat(2, 10, 100),
            'amount_limit' => null,
        ];
    }
}
