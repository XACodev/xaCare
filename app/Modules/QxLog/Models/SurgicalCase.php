<?php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Admission;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(\Database\Factories\SurgicalCaseFactory::class)]
class SurgicalCase extends Model implements HasHospital
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasSlug;

    /**
     * Estandarizar textos a Title Case al guardar.
     */
    protected function patientName(): Attribute
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
        'procedure_type_id',
        'is_videosurgery',

        'calculated_amount',
        'pricing_snapshot',
        'status',

        'operating_room_id',
        'surgery_status_id',
        'is_draft',
    ];

    protected $casts = [
        'procedure_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'is_videosurgery' => 'boolean',
        'pricing_snapshot' => 'array',      // JSON ↔ array
        'calculated_amount' => 'decimal:2', // siempre 2 decimales
        'is_draft' => 'boolean',
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

    public function operatingRoom()
    {
        return $this->belongsTo(OperatingRoom::class);
    }

    public function surgeryStatus()
    {
        return $this->belongsTo(SurgeryStatus::class);
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(ProcedureType::class);
    }
}
