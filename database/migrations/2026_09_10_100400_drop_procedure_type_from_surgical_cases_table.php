<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->dropColumn('procedure_type');
        });
    }

    public function down(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->string('procedure_type')->nullable()->after('patient_name');
        });
    }
};
