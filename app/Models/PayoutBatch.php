<?php

namespace App\Models;

use App\Contracts\HasHospital;
use App\Contracts\Payable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutBatch extends Model implements HasHospital, Payable
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'payee_id',
        'paid_by_id',
        'paid_at',
        'total_amount',
        'status',
        'void_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function payee()
    {
        return $this->belongsTo(User::class, 'payee_id', 'id');
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(PayoutItem::class, 'payout_batch_id', 'id');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function paidAt(): ?Carbon
    {
        return $this->paid_at;
    }
}
