<?php

namespace App\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(\Database\Factories\HospitalRoomFactory::class)]
class HospitalRoom extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory;

    public static function allowsPlatformAdminWrites(): bool
    {
        return true;
    }

    protected $fillable = [
        'hospital_id',
        'name',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
