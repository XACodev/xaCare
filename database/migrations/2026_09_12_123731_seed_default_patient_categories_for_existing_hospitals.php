<?php

use App\Models\Hospital;
use App\Models\PatientCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Hospital::query()->each(fn (Hospital $hospital) => PatientCategory::seedForHospital($hospital));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se eliminan datos para permitir rollback seguro.
    }
};
