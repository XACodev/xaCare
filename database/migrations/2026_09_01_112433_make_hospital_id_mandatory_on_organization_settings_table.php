<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')
                ->constrained('hospitals')->cascadeOnDelete();
        });

        if (DB::table('hospitals')->count() === 0) {
            // Instalación nueva (migrate fresh sin seed todavía): no hay hospital al
            // que asignar la fila singleton pre-existente. Se limpia; el observer de
            // Hospital crea una fila propia en cuanto se cree el primer hospital.
            DB::table('organization_settings')->delete();
        } else {
            // organization_settings era un singleton global con una única fila.
            // La asignamos al primer hospital existente (HNSC en producción).
            $firstHospitalId = DB::table('hospitals')->orderBy('id')->value('id');
            DB::table('organization_settings')->whereNull('hospital_id')->update(['hospital_id' => $firstHospitalId]);

            // Cualquier hospital adicional sin fila propia recibe una copia de los valores actuales.
            $defaults = DB::table('organization_settings')->orderBy('id')->first();

            DB::table('hospitals')->orderBy('id')->pluck('id')->each(function ($hospitalId) use ($defaults) {
                $exists = DB::table('organization_settings')->where('hospital_id', $hospitalId)->exists();

                if (! $exists && $defaults) {
                    DB::table('organization_settings')->insert([
                        'hospital_id' => $hospitalId,
                        'org_name' => $defaults->org_name,
                        'voucher_legend' => $defaults->voucher_legend,
                        'logo_path' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable(false)->change();
            $table->unique('hospital_id');
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropUnique(['hospital_id']);
            $table->dropForeign(['hospital_id']);
            $table->dropColumn('hospital_id');
        });
    }
};
