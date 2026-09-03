<?php

namespace Database\Factories;

use App\Modules\QxLog\Models\PayoutBatch;
use App\Modules\QxLog\Models\SurgicalAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\PayoutItem>
 */
class PayoutItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payout_batch_id' => PayoutBatch::factory(),
            'surgical_assignment_id' => SurgicalAssignment::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 1000),
            'snapshot' => [],
        ];
    }
}
