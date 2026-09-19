<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_business_day',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_time' => 'datetime:H:i:s',
            'end_time' => 'datetime:H:i:s',
            'is_business_day' => 'boolean',
        ];
    }

    public function scopeForDay($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
}
