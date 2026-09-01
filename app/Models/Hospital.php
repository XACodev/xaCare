<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'plan', 'features', 'is_active'];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function organizationSetting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $hospital) {
            OrganizationSetting::create([
                'hospital_id' => $hospital->id,
                'org_name' => $hospital->name,
                'voucher_legend' => 'Por honorarios correspondientes a servicios de instrumentación prestados en procedimientos quirúrgicos.',
            ]);
        });
    }
}
