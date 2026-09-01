<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OrganizationSetting extends Model
{
    use SoftDeletes;

    const CACHE_KEY = 'organization_settings.current';

    protected $fillable = [
        'org_name',
        'voucher_legend',
        'logo_path',
    ];

    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->firstOrCreate([]);
        });
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk(self::logoDisk())->url($this->logo_path);
    }

    /**
     * R2 no tiene credenciales en entornos locales, asi que el logo cae
     * al disco publico local para poder probar la subida sin depender de R2.
     */
    public static function logoDisk(): string
    {
        return filled(config('filesystems.disks.r2.key')) ? 'r2' : 'public';
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
