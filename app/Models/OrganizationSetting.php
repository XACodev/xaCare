<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class OrganizationSetting extends Model
{
    use SoftDeletes;

    const CACHE_KEY = 'organization_settings.current';

    protected $fillable = [
        'org_name',
        'voucher_legend',
    ];

    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->firstOrCreate([]);
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
