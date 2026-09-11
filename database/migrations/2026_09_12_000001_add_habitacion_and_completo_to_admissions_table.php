<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('habitacion')->nullable()->after('sala_ingreso');
            $table->boolean('completo')->default(true)->after('qr_printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropColumn(['habitacion', 'completo']);
        });
    }
};
