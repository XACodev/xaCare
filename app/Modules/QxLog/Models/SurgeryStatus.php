<?php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UseFactory(\Database\Factories\SurgeryStatusFactory::class)]
class SurgeryStatus extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'name',
        'slug',
        'color',
        'sort_order',
        'is_default',
        'is_completed',
        'is_cancelled',
        'active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_completed' => 'boolean',
        'is_cancelled' => 'boolean',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $status) {
            if (! $status->slug && $status->name) {
                $status->slug = Str::slug($status->name);
            }
        });
    }

    public function surgicalCases(): HasMany
    {
        return $this->hasMany(SurgicalCase::class);
    }
}
