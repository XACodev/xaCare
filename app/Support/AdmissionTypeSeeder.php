<?php

namespace App\Support;

use App\Models\AdmissionType;
use App\Models\Hospital;

final class AdmissionTypeSeeder
{
    /** @return array<int, array{name: string, slug: string, visible_sections: array<int, string>}> */
    public static function defaults(): array
    {
        $identidadFija = []; // identidad básica y fecha de ingreso no son una "sección" configurable

        return [
            ['name' => 'COEX', 'slug' => 'coex', 'visible_sections' => [...$identidadFija, 'seguro']],
            ['name' => 'Emergencia hábil', 'slug' => 'emergencia-habil', 'visible_sections' => [...$identidadFija, 'contactos_emergencia']],
            ['name' => 'Emergencia inhábil', 'slug' => 'emergencia-inhabil', 'visible_sections' => [...$identidadFija, 'contactos_emergencia']],
            ['name' => 'COEX en emergencia', 'slug' => 'coex-emergencia', 'visible_sections' => [...$identidadFija, 'contactos_emergencia', 'seguro']],
            ['name' => 'Urgencia', 'slug' => 'urgencia', 'visible_sections' => [...$identidadFija, 'contactos_emergencia']],
            ['name' => 'Hospitalización', 'slug' => 'hospitalizacion', 'visible_sections' => [
                ...$identidadFija,
                'nacionalidad_documento', 'lugar_nacimiento_direccion', 'estado_civil', 'familiares',
                'contactos_emergencia', 'seguro', 'sala_habitacion', 'medico_responsable',
            ]],
            ['name' => 'Hospitalización de emergencia', 'slug' => 'hospitalizacion-emergencia', 'visible_sections' => [
                ...$identidadFija, 'contactos_emergencia', 'seguro', 'sala_habitacion', 'medico_responsable',
            ]],
        ];
    }

    public static function seedDefaultsFor(Hospital $hospital): void
    {
        foreach (self::defaults() as $sortOrder => $tipo) {
            AdmissionType::create([
                'hospital_id' => $hospital->id,
                'name' => $tipo['name'],
                'slug' => $tipo['slug'],
                'active' => true,
                'sort_order' => $sortOrder,
                'es_ingreso_rapido_default' => false,
                'visible_sections' => $tipo['visible_sections'],
                'required_sections' => $tipo['visible_sections'],
            ]);
        }
    }
}
