<?php

namespace Database\Factories;

use App\Models\BusinessHoliday;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessHolidayFactory extends Factory
{
    protected $model = BusinessHoliday::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'date' => $this->faker->date(),
            'name' => $this->faker->words(2, true),
        ];
    }
}
