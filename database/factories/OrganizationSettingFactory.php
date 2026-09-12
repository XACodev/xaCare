<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\OrganizationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationSettingFactory extends Factory
{
    protected $model = OrganizationSetting::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'org_name' => $this->faker->company(),
            'voucher_legend' => $this->faker->sentence(),
            'logo_path' => null,
        ];
    }
}
