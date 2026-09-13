<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(\Database\Factories\AdmissionTypeFactory::class)]
class AdmissionType extends Model
{
    use BelongsToTenant, HasFactory;

    public static function allowsPlatformAdminWrites(): bool
    {
        return true;
    }

    protected $fillable = [
        'hospital_id',
        'name',
        'slug',
        'active',
        'sort_order',
        'es_ingreso_rapido_default',
        'visible_sections',
        'required_sections',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'es_ingreso_rapido_default' => 'boolean',
            'visible_sections' => 'array',
            'required_sections' => 'array',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(AdmissionTypeCustomField::class);
    }
}
