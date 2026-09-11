<?php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use App\Support\NameFormatter;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UseFactory(\Database\Factories\ProcedureTypeFactory::class)]
class ProcedureType extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'name',
        'slug',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => NameFormatter::titleCase($v) ?? $v);
    }

    /**
     * Busca un ProcedureType del hospital dado cuyo nombre coincida con $name de forma
     * insensible a mayúsculas/minúsculas Y a acentos (SQLite no tiene collation con
     * acent-folding por defecto, así que la comparación se hace en PHP con Str::ascii()
     * sobre un set de candidatos ya acotado por hospital_id — mismo patrón que usan las
     * búsquedas de paciente/usuario en schedule.blade.php y procedures/create.blade.php).
     * No crea nada: uso seguro para previews sin efectos secundarios.
     */
    public static function findByNameFor(int $hospitalId, string $name): ?self
    {
        $normalized = Str::ascii(Str::lower(trim($name)));
        if ($normalized === '') {
            return null;
        }

        return static::withoutGlobalScopes()
            ->where('hospital_id', $hospitalId)
            ->get()
            ->first(fn (self $candidate) => Str::ascii(Str::lower($candidate->name)) === $normalized);
    }

    /**
     * Igual que findByNameFor(), pero crea el ProcedureType si no existe todavía. Punto
     * único de "resolve-or-create" usado por los 4 lugares que antes duplicaban esta
     * lógica (procedures/create, procedures/edit, surgeries/schedule y
     * pricing/procedure-types) con una comparación accent-sensitive que fragmentaba el
     * catálogo (ej. "Cesarea" vs "Cesárea" quedaban como dos tipos distintos).
     */
    public static function resolveOrCreateFor(int $hospitalId, string $name): self
    {
        $name = trim($name);

        return static::findByNameFor($hospitalId, $name)
            ?? static::create(['hospital_id' => $hospitalId, 'name' => $name]);
    }
}
