<?php

namespace Database\Factories;

use App\Modules\QxLog\Models\RateModifier;
use App\Modules\QxLog\Models\RoleRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\QxLog\Models\RateModifier>
 */
class RateModifierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'role_rate_id' => RoleRate::factory(),
            'name' => 'Nocturno',
            'trigger_type' => RateModifier::TRIGGER_TIME_WINDOW,
            'trigger_config' => ['start' => '22:00', 'end' => '06:00'],
            'rate_type' => RateModifier::RATE_FIXED_AMOUNT,
            'amount' => 350,
            'active' => true,
            'sort_order' => 0,
        ];
    }

    public function durationGte(int $minutes): static
    {
        return $this->state(fn () => [
            'name' => 'Caso largo',
            'trigger_type' => RateModifier::TRIGGER_DURATION_GTE,
            'trigger_config' => ['minutes' => $minutes],
        ]);
    }

    public function manualToggle(string $name): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE,
            'trigger_config' => [],
        ]);
    }
}
