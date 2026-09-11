<?php
// app/Modules/QxLog/Models/SurgeryQuote.php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(\Database\Factories\SurgeryQuoteFactory::class)]
class SurgeryQuote extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'surgical_case_id',
        'staff_fee',
        'hospital_cost',
        'hospital_cost_note',
        'total',
        'version',
        'status',
        'created_by_id',
    ];

    protected $casts = [
        'staff_fee' => 'decimal:2',
        'hospital_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'version' => 'integer',
    ];

    public static function booted(): void
    {
        static::saving(function (self $quote) {
            $quote->total = (float) $quote->staff_fee + (float) $quote->hospital_cost;
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function surgicalCase(): BelongsTo
    {
        return $this->belongsTo(SurgicalCase::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public static function latestFor(int $hospitalId, int $patientId): ?self
    {
        return static::query()
            ->where('hospital_id', $hospitalId)
            ->where('patient_id', $patientId)
            ->where('status', '!=', 'superseded')
            ->latest('version')
            ->first();
    }

    /**
     * Si la ultima cotizacion del paciente esta en draft, la edita en el
     * mismo registro (sin bump de version). Si no hay ninguna, o la ultima
     * ya fue emitida, crea una fila nueva con version+1 y marca la anterior
     * (si existia) como superseded — asi nunca se pierde lo que se le
     * mostro al paciente en una version ya entregada.
     */
    public static function saveDraftOrNewVersion(array $attributes): self
    {
        $previous = static::latestFor($attributes['hospital_id'], $attributes['patient_id']);

        if ($previous && $previous->status === 'draft') {
            $previous->fill($attributes);
            $previous->save();

            return $previous;
        }

        $attributes['version'] = ($previous?->version ?? 0) + 1;
        $attributes['status'] = 'draft';
        $attributes['surgical_case_id'] = $attributes['surgical_case_id'] ?? $previous?->surgical_case_id;

        $new = static::create($attributes);

        if ($previous) {
            $previous->update(['status' => 'superseded']);
        }

        return $new;
    }

    public function markIssued(): void
    {
        $this->update(['status' => 'issued']);
    }

    public function attachToSurgicalCase(SurgicalCase $case): void
    {
        $this->update(['surgical_case_id' => $case->id]);
    }
}
