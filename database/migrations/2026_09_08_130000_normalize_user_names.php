<?php

use App\Models\User;
use App\Support\NameFormatter;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Normaliza a Title Case los nombres ya guardados (ej. "JUan ValdEz" -> "Juan Valdez"),
     * usando el mismo formateador que ya aplica el mutator de User al guardar.
     * Idempotente: si ya está normalizado, la actualización es un no-op.
     */
    public function up(): void
    {
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
