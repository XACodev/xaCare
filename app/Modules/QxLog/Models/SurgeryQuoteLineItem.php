<?php
// app/Modules/QxLog/Models/SurgeryQuoteLineItem.php

namespace App\Modules\QxLog\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(\Database\Factories\SurgeryQuoteLineItemFactory::class)]
class SurgeryQuoteLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'surgery_quote_id',
        'surgical_role_id',
        'label',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(SurgeryQuote::class, 'surgery_quote_id');
    }

    public function surgicalRole(): BelongsTo
    {
        return $this->belongsTo(SurgicalRole::class);
    }
}
