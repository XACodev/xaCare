<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RoleRate extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'hospital_id',
        'surgical_role_id',
        'user_id',
        'procedure_type',
        'base_rate',
        'active',
    ];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function surgicalRole(): BelongsTo
    {
        return $this->belongsTo(SurgicalRole::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(RateModifier::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['base_rate', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
