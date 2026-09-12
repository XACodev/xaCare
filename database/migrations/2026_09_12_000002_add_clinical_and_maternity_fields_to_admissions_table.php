<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('medico_colegiado')->nullable()->after('medico_responsable');

            $table->string('maternidad_no_hijo')->nullable()->after('medico_colegiado');
            $table->date('maternidad_fecha_nacimiento')->nullable()->after('maternidad_no_hijo');
            $table->time('maternidad_hora')->nullable()->after('maternidad_fecha_nacimiento');
            $table->string('maternidad_sexo')->nullable()->after('maternidad_hora');
            $table->text('maternidad_condiciones_egreso')->nullable()->after('maternidad_sexo');
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropColumn([
                'medico_colegiado',
                'maternidad_no_hijo',
                'maternidad_fecha_nacimiento',
                'maternidad_hora',
                'maternidad_sexo',
                'maternidad_condiciones_egreso',
            ]);
        });
    }
};
