<?php
// database/migrations/2026_09_11_100000_create_surgery_quotes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surgery_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('surgical_case_id')->nullable()->constrained('surgical_cases')->nullOnDelete();
            $table->string('slug', 12)->unique();
            $table->decimal('staff_fee', 10, 2)->default(0);
            $table->decimal('hospital_cost', 10, 2)->default(0);
            $table->text('hospital_cost_note')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'issued', 'superseded'])->default('draft');
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['hospital_id', 'patient_id']);
            $table->index(['hospital_id', 'surgical_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surgery_quotes');
    }
};
