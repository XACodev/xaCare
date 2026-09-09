<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un borrador de cirugía programada puede guardarse con solo la fecha; hora y
     * paciente se completan después. Estas columnas se crearon NOT NULL pensando
     * únicamente en el registro retroactivo de `procedures/create`.
     */
    public function up(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
            $table->string('patient_name')->nullable()->change();
            $table->string('procedure_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->time('start_time')->nullable(false)->change();
            $table->time('end_time')->nullable(false)->change();
            $table->string('patient_name')->nullable(false)->change();
            $table->string('procedure_type')->nullable(false)->change();
        });
    }
};
