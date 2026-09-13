<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(\Database\Factories\AdmissionTypeCustomFieldFactory::class)]
class AdmissionTypeCustomField extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'admission_type_id',
        'step',
        'label',
        'slug',
        'field_type',
        'options',
        'required',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'options' => 'array',
            'required' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function admissionType(): BelongsTo
    {
        return $this->belongsTo(AdmissionType::class);
    }
}
