<?php

namespace App\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RateModifier extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory, LogsActivity;

    public const TRIGGER_TIME_WINDOW = 'time_window';
    public const TRIGGER_DURATION_GTE = 'duration_gte';
    public const TRIGGER_MANUAL_TOGGLE = 'manual_toggle';

    public const RATE_FIXED_AMOUNT = 'fixed_amount';
    public const RATE_MULTIPLIER = 'multiplier';

    protected $fillable = [
        'hospital_id',
        'role_rate_id',
        'name',
        'trigger_type',
        'trigger_config',
        'rate_type',
        'amount',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'amount' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roleRate(): BelongsTo
    {
        return $this->belongsTo(RoleRate::class);
    }

    public function isManual(): bool
    {
        return $this->trigger_type === self::TRIGGER_MANUAL_TOGGLE;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'active', 'trigger_config'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
