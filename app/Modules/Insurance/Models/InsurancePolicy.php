<?php

namespace App\Modules\Insurance\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(\Database\Factories\InsurancePolicyFactory::class)]
class InsurancePolicy extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'insurer_id',
        'patient_id',
        'policy_number',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class);
    }
}
