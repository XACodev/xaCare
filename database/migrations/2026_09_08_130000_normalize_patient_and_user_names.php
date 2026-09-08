<?php

use App\Models\Patient;
use App\Models\User;
use App\Support\NameFormatter;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Normaliza a Title Case los nombres ya guardados (ej. "JUan ValdEz" -> "Juan Valdez"),
     * usando el mismo formateador que ya aplican los mutators de Patient y User al guardar.
     * Idempotente: si ya está normalizado, la actualización es un no-op.
     */
    public function up(): void
    {
        Patient::withTrashed()
            ->orderBy('id')
            ->chunkById(200, function ($patients): void {
                foreach ($patients as $patient) {
                    $patient->newQueryWithoutScopes()->where('id', $patient->id)->update([
                        'primer_apellido' => NameFormatter::titleCase($patient->getRawOriginal('primer_apellido')),
                        'segundo_apellido' => NameFormatter::titleCase($patient->getRawOriginal('segundo_apellido')),
                        'primer_nombre' => NameFormatter::titleCase($patient->getRawOriginal('primer_nombre')),
                        'segundo_nombre' => NameFormatter::titleCase($patient->getRawOriginal('segundo_nombre')),
                    ]);
                }
            });

        User::withTrashed()
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $user->newQueryWithoutScopes()->where('id', $user->id)->update([
                        'name' => NameFormatter::titleCase($user->getRawOriginal('name')),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Intencionalmente no reversible: no se guarda el valor original antes de normalizar.
    }
};
