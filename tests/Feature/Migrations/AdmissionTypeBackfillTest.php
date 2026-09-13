<?php
// tests/Feature/Migrations/AdmissionTypeBackfillTest.php

use App\Models\Admission;
use App\Models\AdmissionType;
use App\Models\Hospital;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

it('backfills admission_type_id from the old tipo_atencion enum value', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital)->create();

    // RefreshDatabase ya corrió esta migración de forma automática (junto con el resto
    // del set) antes de que arranque el test, así que el esquema ya no tiene
    // `tipo_atencion`. Se revierte con down() para simular el estado legado, insertar
    // datos de prueba con la columna vieja, y volver a invocar up() para probar el backfill.
    $migration = require database_path('migrations/2026_09_13_000004_add_admission_type_id_to_admissions_table.php');
    $migration->down();

    DB::table('admissions')->insert([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'tipo_atencion' => 'emergencia',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);

    $migration->up();

    $admission = Admission::withoutGlobalScopes()->where('hospital_id', $hospital->id)->firstOrFail();
    $tipoEsperado = AdmissionType::withoutGlobalScopes()
        ->where('hospital_id', $hospital->id)
        ->where('slug', 'emergencia-habil')
        ->firstOrFail();

    expect($admission->admission_type_id)->toBe($tipoEsperado->id);
    expect(\Illuminate\Support\Facades\Schema::hasColumn('admissions', 'tipo_atencion'))->toBeFalse();
});
