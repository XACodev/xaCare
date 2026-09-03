<?php
// app/Modules/QxLog/Models/SurgicalAssignment.php
namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Contracts\Payable;
use App\Contracts\Priced;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[UseFactory(\Database\Factories\SurgicalAssignmentFactory::class)]
class SurgicalAssignment extends Model implements HasHospital, Priced, Payable
{
    use BelongsToTenant, HasFactory, LogsActivity;

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

    public function price(): float
    {
        return (float) $this->calculated_amount;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function paidAt(): ?Carbon
    {
        return $this->payoutItem?->payoutBatch?->paid_at;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['calculated_amount', 'is_courtesy', 'note', 'status', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
