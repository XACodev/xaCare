<?php

namespace App\Models;

use App\Enums\PatientCategory as PatientCategoryEnum;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientCategory extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'code',
        'name',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function defaultCategories(): array
    {
        return [
            ['code' => PatientCategoryEnum::RECIEN_NACIDO->value, 'name' => PatientCategoryEnum::RECIEN_NACIDO->label(), 'sort_order' => 10],
            ['code' => PatientCategoryEnum::NEONATO->value, 'name' => PatientCategoryEnum::NEONATO->label(), 'sort_order' => 20],
            ['code' => PatientCategoryEnum::NINO->value, 'name' => PatientCategoryEnum::NINO->label(), 'sort_order' => 30],
            ['code' => PatientCategoryEnum::ADOLESCENTE->value, 'name' => PatientCategoryEnum::ADOLESCENTE->label(), 'sort_order' => 40],
            ['code' => PatientCategoryEnum::ADULTO->value, 'name' => PatientCategoryEnum::ADULTO->label(), 'sort_order' => 50],
            ['code' => PatientCategoryEnum::ADULTO_MAYOR->value, 'name' => PatientCategoryEnum::ADULTO_MAYOR->label(), 'sort_order' => 60],
        ];
    }

    public static function seedForHospital(Hospital $hospital): void
    {
        foreach (self::defaultCategories() as $category) {
            self::firstOrCreate(
                ['hospital_id' => $hospital->id, 'code' => $category['code']],
                [
                    'name' => $category['name'],
                    'active' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );
        }
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
