<?php

use App\Models\Patient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Identificador aleatorio no adivinable para la URL de detalle del paciente, en vez del
     * id autoincremental. Backfill idempotente: solo genera slug para filas que no lo tengan.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('slug', 12)->nullable()->unique()->after('id');
        });

        Patient::withTrashed()
            ->whereNull('slug')
            ->orderBy('id')
            ->chunkById(200, function ($patients): void {
                foreach ($patients as $patient) {
                    do {
                        $slug = Str::random(12);
                    } while (Patient::withTrashed()->where('slug', $slug)->exists());

                    $patient->newQueryWithoutScopes()->where('id', $patient->id)->update(['slug' => $slug]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
