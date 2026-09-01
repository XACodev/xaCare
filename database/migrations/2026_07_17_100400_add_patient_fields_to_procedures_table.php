<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')->constrained('hospitals')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->after('hospital_id')->constrained('patients')->nullOnDelete();
            $table->foreignId('admission_id')->nullable()->after('patient_id')->constrained('admissions')->nullOnDelete();

            $table->index(['hospital_id', 'status'], 'procedures_hospital_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['admission_id']);
            $table->dropIndex('procedures_hospital_status_idx');
            $table->dropColumn(['hospital_id', 'patient_id', 'admission_id']);
        });
    }
};
