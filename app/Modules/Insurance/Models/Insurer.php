<?php

namespace App\Modules\Insurance\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(\Database\Factories\InsurerFactory::class)]
class Insurer extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function policies(): HasMany
    {
        return $this->hasMany(InsurancePolicy::class);
    }
}
