<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\PayoutBatch>
 */
class PayoutBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payee_id' => User::factory(),
            'paid_by_id' => User::factory(),
            'paid_at' => now(),
            'total_amount' => $this->faker->randomFloat(2, 100, 1000),
            'status' => 'active',
        ];
    }
}
