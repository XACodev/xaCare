<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'hospital_id', 'expediente_no',
        'primer_apellido', 'segundo_apellido', 'primer_nombre', 'segundo_nombre',
        'dpi', 'fecha_nacimiento', 'sexo', 'lugar_nacimiento', 'nacionalidad', 'estado_civil',
        'direccion_habitual', 'calle_o_lugar', 'municipio', 'departamento', 'telefono',
        'nombre_padre', 'nombre_madre', 'nombre_conyuge', 'contacto_emergencia',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    protected function primerApellido(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => $v ? ucwords(strtolower($v)) : null);
    }

    protected function primerNombre(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => $v ? ucwords(strtolower($v)) : null);
    }

    public function nombreCompleto(): string
    {
        return trim(collect([
            $this->primer_nombre, $this->segundo_nombre,
            $this->primer_apellido, $this->segundo_apellido,
        ])->filter()->implode(' '));
    }

    public function admissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Admission::class);
    }
}
