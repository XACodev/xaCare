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
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'specialties' => 'array',
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

    public function lineItems(): HasMany
    {
        return $this->hasMany(SurgeryQuoteLineItem::class)->orderBy('sort_order');
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(ProcedureType::class);
    }

    /**
     * Reemplaza todos los renglones de honorarios de la cotización y
     * recalcula staff_fee/total. $items: list<array{surgical_role_id: ?int, label: string, amount: float}>.
     */
    public function syncLineItems(array $items): void
    {
        $this->lineItems()->delete();

        foreach (array_values($items) as $index => $item) {
            $this->lineItems()->create([
                'surgical_role_id' => $item['surgical_role_id'] ?? null,
                'label' => $item['label'],
                'amount' => $item['amount'],
                'sort_order' => $index,
            ]);
        }

        $this->staff_fee = $this->lineItems()->sum('amount');
        $this->save();
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
     *
     * `latestFor()` busca solo por patient_id, sin distinguir episodio
     * quirurgico: un mismo paciente puede tener cotizaciones de cirugias
     * completamente distintas a lo largo del tiempo. Por eso $previous solo
     * se trata como "la misma cotizacion en progreso" cuando su
     * surgical_case_id coincide con el que el llamador pasa explicitamente
     * (o ambos son null, caso de una cotizacion sin cirugia asociada
     * todavia). El surgical_case_id nunca se hereda implicitamente de
     * $previous: solo se usa el que el llamador haya pasado explicitamente
     * (p.ej. al revisar/crear una nueva version de una cotizacion ya
     * conocida).
     */
    public static function saveDraftOrNewVersion(array $attributes): self
    {
        $previous = static::latestFor($attributes['hospital_id'], $attributes['patient_id']);
        $surgicalCaseId = $attributes['surgical_case_id'] ?? null;
        $sameEpisode = $previous && $previous->surgical_case_id === $surgicalCaseId;

        if ($sameEpisode && $previous->status === 'draft') {
            $previous->fill($attributes);
            $previous->save();

            return $previous;
        }

        $attributes['version'] = ($sameEpisode ? $previous->version : 0) + 1;
        $attributes['status'] = 'draft';
        $attributes['surgical_case_id'] = $surgicalCaseId;

        $new = static::create($attributes);

        if ($sameEpisode) {
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
