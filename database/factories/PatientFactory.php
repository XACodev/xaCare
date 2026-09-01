<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'primer_apellido' => ucfirst($this->faker->word()),
            'segundo_apellido' => ucfirst($this->faker->word()),
            'primer_nombre' => ucfirst($this->faker->word()),
            'dpi' => (string) $this->faker->numerify('#############'),
            'fecha_nacimiento' => $this->faker->date(),
            'sexo' => $this->faker->randomElement(['M', 'F']),
        ];
    }
}
