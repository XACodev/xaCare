<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HospitalFactory extends Factory
{
    protected $model = Hospital::class;

    public function definition(): array
    {
        $name = 'Hospital '.ucwords($this->faker->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'plan' => 'basic',
            'features' => config('billing.plans.basic.features', []),
            'is_active' => true,
            'subscription_status' => 'active',
            'trial_ends_at' => null,
        ];
    }
}
