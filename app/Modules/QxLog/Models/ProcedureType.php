<?php

namespace App\Modules\QxLog\Models;

use App\Contracts\HasHospital;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSlug;
use App\Support\NameFormatter;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(\Database\Factories\ProcedureTypeFactory::class)]
class ProcedureType extends Model implements HasHospital
{
    use BelongsToTenant, HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'name',
        'slug',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => NameFormatter::titleCase($v) ?? $v);
    }
}
