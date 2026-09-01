<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutBatch extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'instrumentist_id',
        'paid_by_id',
        'paid_at',
        'total_amount',
        'status',
        'void_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function instrumentist()
    {
        return $this->belongsTo(User::class, 'instrumentist_id', 'id');
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(PayoutItem::class, 'payout_batch_id', 'id');
    }
}
