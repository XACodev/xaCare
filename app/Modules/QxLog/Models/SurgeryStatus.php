<?php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UseFactory(\Database\Factories\SurgeryStatusFactory::class)]
class SurgeryStatus extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory;

    /** Ver OperatingRoom::seedDefaultFor(): mismo problema, mismo remedio. */
    public static function seedDefaultsFor(Hospital $hospital): void
    {
        if (static::withoutGlobalScopes()->where('hospital_id', $hospital->id)->exists()) {
            return;
        }

        $statuses = [
            ['name' => 'Programada', 'slug' => 'programada', 'sort_order' => 0, 'is_default' => true],
            ['name' => 'Confirmada', 'slug' => 'confirmada', 'sort_order' => 1],
            ['name' => 'En curso', 'slug' => 'en-curso', 'sort_order' => 2],
            ['name' => 'Completada', 'slug' => 'completada', 'sort_order' => 3, 'is_completed' => true],
            ['name' => 'Cancelada', 'slug' => 'cancelada', 'sort_order' => 4, 'is_cancelled' => true],
        ];

        foreach ($statuses as $status) {
            static::withoutGlobalScopes()->create([
                'hospital_id' => $hospital->id,
                'name' => $status['name'],
                'slug' => $status['slug'],
                'sort_order' => $status['sort_order'],
                'is_default' => $status['is_default'] ?? false,
                'is_completed' => $status['is_completed'] ?? false,
                'is_cancelled' => $status['is_cancelled'] ?? false,
                'active' => true,
            ]);
        }
    }

    public static function allowsPlatformAdminWrites(): bool
    {
        return true;
    }

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
