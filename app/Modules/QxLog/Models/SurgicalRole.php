<?php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UseFactory(\Database\Factories\SurgicalRoleFactory::class)]
class SurgicalRole extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'name',
        'slug',
        'is_payable',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'is_payable' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $role) {
            if (! $role->slug && $role->name) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    public function roleRates(): HasMany
    {
        return $this->hasMany(RoleRate::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SurgicalAssignment::class);
    }
}
