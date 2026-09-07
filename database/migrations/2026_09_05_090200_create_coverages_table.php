<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('insurance_policy_id')->constrained('insurance_policies')->cascadeOnDelete();

            $table->string('type'); // p.ej. hospitalizacion, consulta, medicamentos, cirugia
            $table->decimal('percentage', 5, 2)->nullable(); // % cubierto por la aseguradora
            $table->decimal('amount_limit', 10, 2)->nullable(); // tope monetario, si aplica
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['hospital_id', 'insurance_policy_id'], 'coverages_hospital_policy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverages');
    }
};
