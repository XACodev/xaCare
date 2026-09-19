<?php

namespace Database\Factories;

use App\Models\BusinessHour;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessHourFactory extends Factory
{
    protected $model = BusinessHour::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'start_time' => '07:00',
            'end_time' => '18:00',
            'is_business_day' => true,
        ];
    }
}
