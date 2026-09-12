<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use App\Support\NameFormatter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasSlug;

    protected $fillable = [
        'hospital_id', 'expediente_no',
        'primer_apellido', 'segundo_apellido', 'primer_nombre', 'segundo_nombre',
        'dpi', 'id_type', 'id_country',
        'fecha_nacimiento', 'sexo', 'lugar_nacimiento', 'nacionalidad', 'estado_civil',
        'es_recien_nacido', 'madre_paciente_id',
        'direccion_habitual', 'calle_o_lugar', 'municipio', 'departamento',
        'telefono', 'telefono_casa', 'emergency_contacts',
        'nombre_padre', 'nombre_madre', 'nombre_conyuge', 'contacto_emergencia',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'es_recien_nacido' => 'boolean',
        'emergency_contacts' => 'array',
    ];

    protected function primerApellido(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => NameFormatter::titleCase($v));
    }

    protected function segundoApellido(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => NameFormatter::titleCase($v));
    }

    protected function primerNombre(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => NameFormatter::titleCase($v));
    }

    protected function segundoNombre(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => NameFormatter::titleCase($v));
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

    public function madrePaciente(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'madre_paciente_id');
    }

    public function hijosRecienNacidos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'madre_paciente_id');
    }
}
