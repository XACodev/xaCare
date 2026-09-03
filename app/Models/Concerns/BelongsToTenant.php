<?php

namespace App\Models\Concerns;

use App\Models\Hospital;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->hospital_id && Auth::hasUser() && Auth::user()?->hospital_id) {
                $model->hospital_id = Auth::user()->hospital_id;
            }

            // User tiene su propia validación en saving (permite platform admins con
            // hospital_id null). El resto de modelos tenant requieren hospital_id cuando
            // hay un usuario autenticado; sin usuario (migraciones/seeders/comandos)
            // dejamos que la BD aplique sus propias constraints.
            if ($model instanceof User) {
                return;
            }

            if (Auth::hasUser() && ! $model->hospital_id) {
                abort(422, 'No se puede crear '.class_basename($model).' sin un hospital asignado.');
            }
        });

        static::saving(function ($model) {
            static::abortIfPlatformAdminWriteBlocked();
        });

        static::deleting(function ($model) {
            static::abortIfPlatformAdminWriteBlocked();
        });
    }

    protected static function abortIfPlatformAdminWriteBlocked(): void
    {
        if (static::allowsPlatformAdminWrites()) {
            return;
        }

        if (Auth::hasUser() && Auth::user()?->is_platform_admin) {
            abort(403, 'Administrador de plataforma es de solo lectura sobre datos operativos de hospitales.');
        }
    }

    /**
     * Los modelos que el super admin sí necesita poder escribir (ej. User, para gestión de
     * administradores de hospital) deben sobreescribir esto a `true`.
     */
    public static function allowsPlatformAdminWrites(): bool
    {
        return false;
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
