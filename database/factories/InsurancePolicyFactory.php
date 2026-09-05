<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Modules\Insurance\Models\Insurer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Insurance\Models\InsurancePolicy>
 */
class InsurancePolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'insurer_id' => Insurer::factory(),
            'patient_id' => Patient::factory(),
            'policy_number' => strtoupper($this->faker->bothify('POL-####??')),
            'start_date' => $this->faker->date(),
            'end_date' => null,
            'status' => 'active',
        ];
    }
}
