<?php

namespace Database\Factories;

use App\Modules\QxLog\Models\ProcedureType;
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
            // Closure de atributo: Eloquent la invoca con los atributos ya resueltos
            // (incluyendo overrides explícitos como 'hospital_id' pasados a create()), así
            // que el ProcedureType generado hereda el mismo hospital que el SurgicalCase aun
            // en tests sin usuario autenticado (donde el creating hook de BelongsToTenant no
            // tiene de dónde tomar el hospital_id por defecto).
            'procedure_type_id' => function (array $attributes) {
                $factory = ProcedureType::factory();
                if (! empty($attributes['hospital_id'])) {
                    $factory = $factory->state(['hospital_id' => $attributes['hospital_id']]);
                }

                return $factory->create()->id;
            },
            'is_videosurgery' => $this->faker->boolean(),
            'calculated_amount' => $this->faker->randomFloat(2, 100, 1000),
            'pricing_snapshot' => [],
            'status' => 'pending',
            'is_draft' => false,
        ];
    }
}
