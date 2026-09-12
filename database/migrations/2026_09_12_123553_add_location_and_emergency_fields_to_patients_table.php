<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('id_type')->nullable()->after('dpi');
            $table->string('id_country')->nullable()->after('id_type');
            $table->boolean('es_recien_nacido')->default(false)->after('estado_civil');
            $table->foreignId('madre_paciente_id')->nullable()->constrained('patients')->nullOnDelete()->after('es_recien_nacido');
            $table->string('telefono_casa')->nullable()->after('telefono');
            $table->json('emergency_contacts')->nullable()->after('telefono_casa');
        });

        // Migrar el contacto de emergencia antiguo (string) al nuevo array JSON.
        DB::table('patients')
            ->whereNotNull('contacto_emergencia')
            ->where('contacto_emergencia', '!=', '')
            ->update([
                'emergency_contacts' => DB::raw("json_array(json_object('nombre', contacto_emergencia, 'telefono', '', 'municipio', '', 'departamento', ''))"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['id_type', 'id_country', 'es_recien_nacido', 'telefono_casa', 'emergency_contacts']);
            $table->dropForeign(['madre_paciente_id']);
            $table->dropColumn('madre_paciente_id');
        });
    }
};
