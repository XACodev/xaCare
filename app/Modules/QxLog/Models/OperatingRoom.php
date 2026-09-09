<?php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(\Database\Factories\OperatingRoomFactory::class)]
class OperatingRoom extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory;

    /**
     * Sin esto, un hospital nuevo (creado por el administrador de plataforma) nunca tiene
     * quirófano por defecto y la validación de choque de horario del tablero de cirugías
     * queda deshabilitada en silencio (operating_room_id se guarda null y el chequeo se
     * salta). Ver también SurgeryStatus::seedDefaultFor().
     */
    public static function seedDefaultFor(Hospital $hospital): void
    {
        if (static::withoutGlobalScopes()->where('hospital_id', $hospital->id)->exists()) {
            return;
        }

        static::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id,
            'name' => 'Principal',
            'is_default' => true,
            'active' => true,
            'sort_order' => 0,
        ]);
    }

    public static function allowsPlatformAdminWrites(): bool
    {
        return true;
    }

    protected $fillable = [
        'hospital_id',
        'name',
        'is_default',
        'default_for_procedure_types',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'default_for_procedure_types' => 'array',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function surgicalCases(): HasMany
    {
        return $this->hasMany(SurgicalCase::class);
    }
}
