<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'patient_id' => Patient::factory(),
            'va_a_quirofano' => false,
            'fecha_ingreso' => now()->toDateString(),
        ];
    }
}
