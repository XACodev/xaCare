<?php
// app/Modules/QxLog/Models/SurgeryQuote.php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(\Database\Factories\SurgeryQuoteFactory::class)]
class SurgeryQuote extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'surgical_case_id',
        'staff_fee',
        'hospital_cost',
        'hospital_cost_note',
        'total',
        'version',
        'status',
        'created_by_id',
    ];

    protected $casts = [
        'staff_fee' => 'decimal:2',
        'hospital_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'version' => 'integer',
    ];

    public static function booted(): void
    {
        static::saving(function (self $quote) {
            $quote->total = (float) $quote->staff_fee + (float) $quote->hospital_cost;
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function surgicalCase(): BelongsTo
    {
        return $this->belongsTo(SurgicalCase::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
