<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingSetting extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'default_rate',
        'video_rate',
        'night_rate',
        'long_case_rate',
        'long_case_threshold_minutes',
        'night_start',
        'night_end',
    ];

    protected $casts = [
        'default_rate' => 'decimal:2',
        'video_rate' => 'decimal:2',
        'night_rate' => 'decimal:2',
        'long_case_rate' => 'decimal:2',
        'long_case_threshold_minutes' => 'integer',
        'night_start' => 'string',
        'night_end' => 'string',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function getNightStartAttribute($value)
    {
        return Carbon::parse($value)->format('H:i');
    }

    public function getNightEndAttribute($value)
    {
        return Carbon::parse($value)->format('H:i');
    }
}
