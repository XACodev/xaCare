<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('role_rate_id')->constrained('role_rates')->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type'); // time_window | duration_gte | manual_toggle
            $table->json('trigger_config')->nullable();
            $table->string('rate_type')->default('fixed_amount'); // fixed_amount | multiplier
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['role_rate_id', 'active'], 'rate_modifiers_role_rate_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_modifiers');
    }
};
