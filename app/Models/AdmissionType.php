<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(\Database\Factories\AdmissionTypeFactory::class)]
class AdmissionType extends Model
{
    use BelongsToTenant, HasFactory;

    public static function allowsPlatformAdminWrites(): bool
    {
        return true;
    }

    protected $fillable = [
        'hospital_id',
        'name',
        'slug',
        'active',
        'sort_order',
        'es_ingreso_rapido_default',
        'business_hour_type_slug',
        'after_hours_type_slug',
        'visible_sections',
        'required_sections',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'es_ingreso_rapido_default' => 'boolean',
            'visible_sections' => 'array',
            'required_sections' => 'array',
        ];
    }

    /**
     * Un tipo de ingreso se considera "base" para el selector simplificado
     * cuando tiene configurado al menos uno de los slugs de resolución
     * hábil/inhábil del addon admissions_business_hours.
     */
    public function isBusinessHourBase(): bool
    {
        return filled($this->business_hour_type_slug) || filled($this->after_hours_type_slug);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(AdmissionTypeCustomField::class);
    }

    /**
     * Color de `<flux:badge :color="...">` determinista para distinguir tipos
     * de ingreso en la UI (chips, filtros). El catálogo es dinámico por
     * hospital (sin columna de color en BD), así que se cicla sobre una
     * paleta fija por `sort_order` en vez de mapear por nombre.
     */
    public function colorToken(): string
    {
        $palette = ['accent', 'red', 'blue', 'violet'];

        return $palette[$this->sort_order % count($palette)];
    }

    /**
     * Misma paleta que `colorToken()`, como clase Tailwind literal (necesario
     * para barras/puntos de color fuera de `flux:badge`, donde Tailwind no
     * puede resolver una clase `bg-{{ $var }}` generada en runtime).
     */
    public function colorBarClass(): string
    {
        $palette = ['bg-accent', 'bg-red-500', 'bg-blue-600', 'bg-violet-600'];

        return $palette[$this->sort_order % count($palette)];
    }
}
