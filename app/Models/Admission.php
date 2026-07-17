<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admission extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'hospital_id', 'patient_id', 'va_a_quirofano',
        'fecha_ingreso', 'hora_ingreso', 'fecha_egreso', 'hora_egreso', 'total_dias',
        'tiene_seguro', 'tiene_igss', 'compania_seguros', 'poliza', 'certificado',
        'impresion_clinica', 'diagnostico_final', 'complicaciones', 'operaciones',
        'sala_ingreso', 'referido_por', 'otras_hospitalizaciones', 'muestra_patologia',
        'medico_responsable',
    ];

    protected $casts = [
        'va_a_quirofano' => 'boolean',
        'tiene_seguro' => 'boolean',
        'tiene_igss' => 'boolean',
        'muestra_patologia' => 'boolean',
        'fecha_ingreso' => 'date',
        'fecha_egreso' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
