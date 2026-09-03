<?php

namespace App\Models;

use App\Contracts\HasHospital;
use App\Contracts\Priced;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutItem extends Model implements HasHospital, Priced
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'payout_batch_id',
        'surgical_assignment_id',
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

    public function surgicalAssignment()
    {
        return $this->belongsTo(SurgicalAssignment::class);
    }

    public function price(): float
    {
        return (float) $this->amount;
    }
}
