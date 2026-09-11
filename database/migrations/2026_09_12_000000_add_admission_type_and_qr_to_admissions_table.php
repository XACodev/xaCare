<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('tipo_atencion')->nullable()->after('patient_id');
            $table->string('qr_token', 64)->nullable()->unique()->after('medico_responsable');
            $table->timestamp('qr_printed_at')->nullable()->after('qr_token');

            $table->index(['hospital_id', 'tipo_atencion'], 'admissions_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex('admissions_tipo_idx');
            $table->dropColumn(['tipo_atencion', 'qr_token', 'qr_printed_at']);
        });
    }
};
