<?php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(\Database\Factories\OperatingRoomFactory::class)]
class OperatingRoom extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'name',
        'is_default',
        'default_for_procedure_types',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'default_for_procedure_types' => 'array',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function surgicalCases(): HasMany
    {
        return $this->hasMany(SurgicalCase::class);
    }
}
