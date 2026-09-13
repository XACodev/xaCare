<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdmissionTypeCustomField>
 */
class AdmissionTypeCustomFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'step' => 2,
            'label' => $this->faker->word(),
            'slug' => $this->faker->unique()->slug(),
            'field_type' => 'texto_corto',
            'required' => false,
            'sort_order' => 0,
            'active' => true,
        ];
    }
}
