<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('surgical_role_id')->constrained('surgical_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('procedure_type')->nullable();
            $table->decimal('base_rate', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['surgical_role_id', 'user_id', 'procedure_type'], 'role_rates_unique_key');
            $table->index(['hospital_id', 'surgical_role_id', 'active'], 'role_rates_hospital_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_rates');
    }
};
