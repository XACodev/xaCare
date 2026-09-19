<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_types', function (Blueprint $table) {
            $table->string('business_hour_type_slug')->nullable()->after('es_ingreso_rapido_default');
            $table->string('after_hours_type_slug')->nullable()->after('business_hour_type_slug');
        });
    }

    public function down(): void
    {
        Schema::table('admission_types', function (Blueprint $table) {
            $table->dropColumn(['business_hour_type_slug', 'after_hours_type_slug']);
        });
    }
};
