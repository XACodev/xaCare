<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\HospitalInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HospitalInvitationFactory extends Factory
{
    protected $model = HospitalInvitation::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'token' => hash('sha256', Str::random(64)),
            'note' => null,
            'invited_by' => null,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'accepted_by' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }
}
