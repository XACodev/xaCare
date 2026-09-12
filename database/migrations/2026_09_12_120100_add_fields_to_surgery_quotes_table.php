<?php
// database/migrations/2026_09_12_120100_add_fields_to_surgery_quotes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surgery_quotes', function (Blueprint $table) {
            $table->foreignId('procedure_type_id')->nullable()->after('surgical_case_id')
                ->constrained('procedure_types')->nullOnDelete();
            $table->json('specialties')->nullable()->after('hospital_cost_note');
            $table->text('internal_note')->nullable()->after('specialties');
        });
    }

    public function down(): void
    {
        Schema::table('surgery_quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procedure_type_id');
            $table->dropColumn(['specialties', 'internal_note']);
        });
    }
};
