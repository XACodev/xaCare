<?php
// database/migrations/2026_09_13_000004_add_admission_type_id_to_admissions_table.php

use App\Models\AdmissionType;
use App\Models\Hospital;
use App\Support\AdmissionTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $legacyToSlug = [
        'emergencia' => 'emergencia-habil',
        'urgencia' => 'urgencia',
        'hospitalizacion' => 'hospitalizacion',
        'coex' => 'coex',
        'coex_emergencia' => 'coex-emergencia',
    ];

    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->foreignId('admission_type_id')->nullable()->after('patient_id')->constrained();
        });

        Hospital::query()->orderBy('id')->each(function (Hospital $hospital) {
            if (AdmissionType::withoutGlobalScopes()->where('hospital_id', $hospital->id)->exists()) {
                return;
            }

            AdmissionTypeSeeder::seedDefaultsFor($hospital);
        });

        DB::table('admissions')->orderBy('id')->each(function (object $admission) {
            $slug = $this->legacyToSlug[$admission->tipo_atencion] ?? null;

            $tipoId = $slug
                ? DB::table('admission_types')
                    ->where('hospital_id', $admission->hospital_id)
                    ->where('slug', $slug)
                    ->value('id')
                : null;

            DB::table('admissions')->where('id', $admission->id)->update([
                'admission_type_id' => $tipoId,
            ]);
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex('admissions_tipo_idx');
            $table->dropColumn('tipo_atencion');
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('tipo_atencion')->nullable()->after('patient_id');
            $table->index(['hospital_id', 'tipo_atencion'], 'admissions_tipo_idx');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admission_type_id');
        });
    }
};
