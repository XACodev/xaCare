<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurgicalCase extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    /**
     * Estandarizar textos a Title Case al guardar.
     */
    protected function patientName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? ucwords(strtolower($value)) : null,
        );
    }

    protected function procedureType(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? ucwords(strtolower($value)) : null,
        );
    }

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'admission_id',
        'procedure_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'patient_name',
        'procedure_type',
        'is_videosurgery',

        'calculated_amount',
        'pricing_snapshot',
        'status',
    ];

    protected $casts = [
        'procedure_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'is_videosurgery' => 'boolean',
        'pricing_snapshot' => 'array',      // JSON ↔ array
        'calculated_amount' => 'decimal:2', // siempre 2 decimales
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function assignments()
    {
        return $this->hasMany(SurgicalAssignment::class);
    }
}
