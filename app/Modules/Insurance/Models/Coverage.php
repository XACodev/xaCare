<?php

namespace App\Modules\Insurance\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(\Database\Factories\CoverageFactory::class)]
class Coverage extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'insurance_policy_id',
        'type',
        'percentage',
        'amount_limit',
        'notes',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount_limit' => 'decimal:2',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class, 'insurance_policy_id');
    }
}
