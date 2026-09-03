<?php
// database/factories/SurgicalAssignmentFactory.php
namespace Database\Factories;

use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\SurgicalAssignment>
 */
class SurgicalAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'surgical_case_id' => SurgicalCase::factory(),
            'surgical_role_id' => SurgicalRole::factory(),
            'user_id' => User::factory(),
            'calculated_amount' => $this->faker->randomFloat(2, 100, 1000),
            'pricing_snapshot' => [],
            'is_courtesy' => false,
            'note' => null,
            'status' => 'pending',
            'payout_item_id' => null,
        ];
    }
}
