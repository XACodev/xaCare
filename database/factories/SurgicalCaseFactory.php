<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\SurgicalCase>
 */
class SurgicalCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'procedure_date' => $this->faker->date(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'duration_minutes' => 120,
            'patient_name' => $this->faker->name(),
            'procedure_type' => $this->faker->word(),
            'is_videosurgery' => $this->faker->boolean(),
            'calculated_amount' => $this->faker->randomFloat(2, 100, 1000),
            'pricing_snapshot' => [],
            'status' => 'pending',
        ];
    }
}
