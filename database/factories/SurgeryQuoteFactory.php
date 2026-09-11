<?php
// database/factories/SurgeryQuoteFactory.php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurgeryQuoteFactory extends Factory
{
    protected $model = SurgeryQuote::class;

    public function definition(): array
    {
        $hospital = Hospital::factory();

        return [
            'hospital_id' => $hospital,
            'patient_id' => Patient::factory()->for($hospital, 'hospital'),
            'surgical_case_id' => null,
            'staff_fee' => $this->faker->randomFloat(2, 500, 10000),
            'hospital_cost' => $this->faker->randomFloat(2, 500, 10000),
            'hospital_cost_note' => null,
            'version' => 1,
            'status' => 'draft',
            'created_by_id' => User::factory(),
        ];
    }
}
