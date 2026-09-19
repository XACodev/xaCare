<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessHoliday extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'date',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }
}
