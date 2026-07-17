<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();

            $table->boolean('va_a_quirofano')->default(false);

            $table->date('fecha_ingreso')->nullable();
            $table->time('hora_ingreso')->nullable();
            $table->date('fecha_egreso')->nullable();
            $table->time('hora_egreso')->nullable();
            $table->unsignedSmallInteger('total_dias')->nullable();

            $table->boolean('tiene_seguro')->default(false);
            $table->boolean('tiene_igss')->default(false);
            $table->string('compania_seguros')->nullable();
            $table->string('poliza')->nullable();
            $table->string('certificado')->nullable();

            $table->text('impresion_clinica')->nullable();
            $table->text('diagnostico_final')->nullable();
            $table->text('complicaciones')->nullable();
            $table->text('operaciones')->nullable();

            $table->string('sala_ingreso')->nullable();
            $table->string('referido_por')->nullable();
            $table->text('otras_hospitalizaciones')->nullable();
            $table->boolean('muestra_patologia')->default(false);
            $table->string('medico_responsable')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['hospital_id', 'va_a_quirofano'], 'admissions_quirofano_idx');
            $table->index(['hospital_id', 'fecha_ingreso'], 'admissions_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
