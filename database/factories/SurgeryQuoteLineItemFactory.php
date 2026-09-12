<?php
// database/factories/SurgeryQuoteLineItemFactory.php

namespace Database\Factories;

use App\Modules\QxLog\Models\SurgeryQuote;
use App\Modules\QxLog\Models\SurgeryQuoteLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurgeryQuoteLineItemFactory extends Factory
{
    protected $model = SurgeryQuoteLineItem::class;

    public function definition(): array
    {
        return [
            'surgery_quote_id' => SurgeryQuote::factory(),
            'surgical_role_id' => null,
            'label' => $this->faker->words(2, true),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'sort_order' => 0,
        ];
    }
}
