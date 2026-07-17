<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'plan', 'features', 'is_active'];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}
