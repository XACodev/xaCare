<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\ProcedureType>
 */
class ProcedureTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'active' => true,
        ];
    }
}
