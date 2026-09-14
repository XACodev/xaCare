<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\AdmissionType;
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
            // Closure de atributo: se invoca con los atributos ya resueltos (incluyendo
            // overrides explícitos de 'hospital_id'), así el AdmissionType generado
            // hereda el mismo hospital que el Admission -- 'hospital_id' es NOT NULL ahí.
            'admission_type_id' => function (array $attributes) {
                $factory = AdmissionType::factory();
                if (! empty($attributes['hospital_id'])) {
                    $factory = $factory->state(['hospital_id' => $attributes['hospital_id']]);
                }

                return $factory->create()->id;
            },
            'va_a_quirofano' => false,
            'fecha_ingreso' => now()->toDateString(),
            'completo' => true,
        ];
    }
}
