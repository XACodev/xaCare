<?php

namespace App\Models\Concerns;

use App\Models\Hospital;
use App\Models\Scopes\TenantScope;
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
        });

        static::saving(function ($model) {
            static::abortIfSuperAdminWriteBlocked();
        });

        static::deleting(function ($model) {
            static::abortIfSuperAdminWriteBlocked();
        });
    }

    protected static function abortIfSuperAdminWriteBlocked(): void
    {
        if (static::allowsSuperAdminWrites()) {
            return;
        }

        if (Auth::hasUser() && Auth::user()?->is_super_admin) {
            abort(403, 'Super admin es de solo lectura sobre datos operativos de hospitales.');
        }
    }

    /**
     * Los modelos que el super admin sí necesita poder escribir (ej. User, para gestión de
     * administradores de hospital) deben sobreescribir esto a `true`.
     */
    public static function allowsSuperAdminWrites(): bool
    {
        return false;
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
