<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OrganizationSetting extends Model
{
    use SoftDeletes;

    const CACHE_KEY = 'organization_settings.current';

    protected $fillable = [
        'hospital_id',
        'org_name',
        'voucher_legend',
        'logo_path',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Configuración del hospital del usuario autenticado.
     */
    public static function current(): self
    {
        return self::forHospital(Auth::user()?->hospital_id);
    }

    /**
     * Configuración de un hospital específico (p. ej. para un super admin
     * revisando un voucher de un hospital que no es el suyo).
     */
    public static function forHospital(?int $hospitalId): self
    {
        abort_if(! $hospitalId, 422, 'No hay un hospital asociado para resolver la configuración de organización.');

        return Cache::rememberForever(self::cacheKey($hospitalId), function () use ($hospitalId) {
            return static::query()->where('hospital_id', $hospitalId)->firstOrFail();
        });
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('r2')->url($this->logo_path);
    }

    protected static function cacheKey(int $hospitalId): string
    {
        return self::CACHE_KEY.'.'.$hospitalId;
    }

    protected static function booted(): void
    {
        static::saved(fn ($model) => Cache::forget(self::cacheKey($model->hospital_id)));
        static::deleted(fn ($model) => Cache::forget(self::cacheKey($model->hospital_id)));
    }
}
