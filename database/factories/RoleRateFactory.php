<?php

namespace Database\Factories;

use App\Modules\QxLog\Models\SurgicalRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\RoleRate>
 */
class RoleRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'surgical_role_id' => SurgicalRole::factory(),
            'user_id' => null,
            'procedure_type' => null,
            'base_rate' => $this->faker->randomFloat(2, 100, 2000),
            'active' => true,
        ];
    }
}
