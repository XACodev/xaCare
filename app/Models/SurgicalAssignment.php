<?php
// app/Models/SurgicalAssignment.php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurgicalAssignment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'surgical_case_id',
        'surgical_role_id',
        'user_id',
        'calculated_amount',
        'pricing_snapshot',
        'is_courtesy',
        'note',
        'status',
        'payout_item_id',
    ];

    protected $casts = [
        'calculated_amount' => 'decimal:2',
        'pricing_snapshot' => 'array',
        'is_courtesy' => 'boolean',
    ];

    public function surgicalCase(): BelongsTo
    {
        return $this->belongsTo(SurgicalCase::class);
    }

    public function surgicalRole(): BelongsTo
    {
        return $this->belongsTo(SurgicalRole::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payoutItem()
    {
        return $this->belongsTo(PayoutItem::class);
    }
}
