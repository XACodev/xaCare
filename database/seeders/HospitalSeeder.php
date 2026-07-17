<?php

namespace Database\Seeders;

use App\Models\Hospital;
use Illuminate\Database\Seeder;

class HospitalSeeder extends Seeder
{
    public function run(): void
    {
        Hospital::firstOrCreate(
            ['slug' => 'hnsc'],
            ['name' => 'Centro Médico y Hospital Nuestra Señora del Carmen', 'plan' => 'basic', 'features' => [], 'is_active' => true],
        );
    }
}
