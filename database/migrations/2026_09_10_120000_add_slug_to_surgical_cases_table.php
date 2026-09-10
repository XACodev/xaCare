<?php

use App\Modules\QxLog\Models\SurgicalCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Identificador aleatorio no adivinable para las URLs de agendar/editar cirugía, en vez
     * del id autoincremental. Backfill idempotente: solo genera slug para filas que no lo tengan.
     */
    public function up(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->string('slug', 12)->nullable()->unique()->after('id');
        });

        SurgicalCase::withTrashed()
            ->whereNull('slug')
            ->orderBy('id')
            ->chunkById(200, function ($cases): void {
                foreach ($cases as $case) {
                    do {
                        $slug = Str::random(12);
                    } while (SurgicalCase::withTrashed()->where('slug', $slug)->exists());

                    $case->newQueryWithoutScopes()->where('id', $case->id)->update(['slug' => $slug]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
