<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PayoutItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'hospital_id',
        'payout_batch_id',
        'procedure_id',
        'amount',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'amount' => 'decimal:2',
    ];

    public function payoutBatch()
    {
        return $this->belongsTo(PayoutBatch::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }
}
